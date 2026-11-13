<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\WhatsAppProvider as WhatsAppProviderContract;
use App\Enums\WhatsAppConnectorStatus;
use App\Enums\WhatsAppProviderResultStatus;
use App\Enums\WhatsAppTemplateStatus;
use App\Models\WhatsAppConnector;
use App\Models\WhatsAppProvider;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppTemplateSubmission;
use App\Models\WhatsAppTemplateVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppCatalogSync
{
    public function __construct(private WhatsAppProviderContract $provider) {}

    /** @return array{created:int,updated:int,unchanged:int,unavailable:int,failed:bool} */
    public function syncConnectors(WhatsAppProvider $provider): array
    {
        $response = $this->provider->getConnectors($provider);
        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'unavailable' => 0, 'failed' => $response->status !== WhatsAppProviderResultStatus::Accepted];
        if ($counts['failed']) {
            Log::warning('WhatsApp connector sync failed.', ['provider_id' => $provider->id, 'http_status' => $response->httpStatus]);

            return $counts;
        }

        $items = $response->data;
        if (array_is_list($items) === false) {
            $items = $items['data'] ?? $items['connectors'] ?? $items['items'] ?? [];
        }
        if (! is_array($items)) {
            return [...$counts, 'failed' => true];
        }

        $seen = [];
        DB::transaction(function () use ($provider, $items, &$counts, &$seen): void {
            foreach ($items as $item) {
                if (is_string($item)) {
                    $item = json_decode($item, true);
                }
                if (! is_array($item)) {
                    continue;
                }
                $externalId = $this->scalar($item, ['phoneNumberId', 'PhoneNumberId', 'connectorId', 'id']);
                if ($externalId === null) {
                    continue;
                }
                $seen[] = $externalId;
                $connector = WhatsAppConnector::firstOrNew(['provider_id' => $provider->id, 'external_id' => $externalId]);
                $wasExisting = $connector->exists;
                $values = [
                    'connector_name' => $this->scalar($item, ['connectorName', 'ConnectorName', 'name']),
                    'sender_number' => $this->scalar($item, ['phoneNumber', 'PhoneNumber']),
                    'waba_id' => $this->scalar($item, ['whatsappBusinessAccountId', 'WhatsAppBusinessAccountId', 'wabaId']),
                    'status' => WhatsAppConnectorStatus::Available,
                    'last_synced_at' => now(),
                ];
                $changed = ! $wasExisting || collect($values)->some(fn ($value, $key) => $connector->getAttribute($key) != $value);
                $connector->fill($values)->save();
                $counts[$wasExisting ? ($changed ? 'updated' : 'unchanged') : 'created']++;
            }
            $counts['unavailable'] = WhatsAppConnector::query()->where('provider_id', $provider->id)
                ->when($seen !== [], fn ($query) => $query->whereNotIn('external_id', $seen))
                ->when($seen === [], fn ($query) => $query)
                ->where('status', '!=', WhatsAppConnectorStatus::Disabled->value)
                ->update(['status' => WhatsAppConnectorStatus::Unavailable->value, 'updated_at' => now()]);
        });

        return $counts;
    }

    /** @return array{created:int,updated:int,unchanged:int,skipped:int,failed:bool} */
    public function syncTemplates(WhatsAppConnector $connector, ?int $templateStatus = null): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => false];
        $offset = 0;
        do {
            $response = $this->provider->getTemplates($connector->provider, (string) $connector->waba_id, $offset, 25, $templateStatus);
            if ($response->status !== WhatsAppProviderResultStatus::Accepted) {
                if (($response->data['errorCode'] ?? $response->data['ErrorCode'] ?? null) === 1028) {
                    break;
                }
                $counts['failed'] = true;
                Log::warning('WhatsApp template sync failed.', ['connector_id' => $connector->id, 'offset' => $offset, 'http_status' => $response->httpStatus]);
                break;
            }
            $items = $response->data;
            $items = $items['templates'] ?? $items['data'] ?? $items['items'] ?? $items;
            if (is_string($items)) {
                $items = json_decode($items, true);
            }
            if (! is_array($items)) {
                $counts['failed'] = true;
                break;
            }
            foreach ($items as $item) {
                if (is_string($item)) {
                    $item = json_decode($item, true);
                }
                if (! is_array($item) || ! $this->storeTemplate($connector, $item, $counts)) {
                    $counts['skipped']++;
                }
            }
            $pageCount = count($items);
            $offset += 25;
        } while ($pageCount === 25 && $offset <= 10000);

        return $counts;
    }

    /** @param array<string,mixed> $item @param array<string,int|bool> $counts */
    private function storeTemplate(WhatsAppConnector $connector, array $item, array &$counts): bool
    {
        $id = $this->scalar($item, ['metaTemplateId', 'MetaTemplateId', 'id', 'templateId']);
        $name = $this->scalar($item, ['name', 'Name', 'templateName']);
        if ($id === null || $name === null) {
            return false;
        }
        if (! isset($item['components']) && ! isset($item['Components'])) {
            $detailResponse = $this->provider->getTemplate($connector->provider, $id);
            if ($detailResponse->status === WhatsAppProviderResultStatus::Accepted) {
                $details = $detailResponse->data['template'] ?? $detailResponse->data['data'] ?? $detailResponse->data;
                if (is_array($details) && array_is_list($details)) {
                    $details = $details[0] ?? [];
                }
                if (is_array($details)) {
                    $item = array_merge($item, $details);
                }
            }
        }
        $components = $item['components'] ?? $item['Components'] ?? null;
        if (is_string($components)) {
            $components = json_decode($components, true) ?: ['raw' => $components];
        }
        $language = $this->scalar($item, ['language', 'Language', 'languageCode']) ?? 'en';
        $category = $this->scalar($item, ['category', 'Category']);
        $status = $this->templateStatus($this->scalar($item, ['status', 'Status', 'templateStatus']));
        $hash = hash('sha256', json_encode([$name, $language, $category, $status->value, $components], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        return DB::transaction(function () use ($connector, $id, $name, $language, $category, $status, $components, $hash, &$counts): bool {
            $template = WhatsAppTemplate::query()->where('connector_id', $connector->id)->where('meta_template_id', $id)->lockForUpdate()->first();
            $created = $template === null;
            $changed = $created || ! hash_equals((string) $template->content_hash, $hash);
            $template ??= new WhatsAppTemplate(['connector_id' => $connector->id, 'meta_template_id' => $id]);
            if ($changed) {
                $template->fill(['name' => $name, 'language' => Str::limit($language, 16, ''), 'category' => $category, 'status' => $status, 'components' => $components, 'content_hash' => $hash, 'last_synced_at' => now()])->save();
                $version = (int) $template->versions()->max('version') + 1;
                WhatsAppTemplateVersion::create(['template_id' => $template->id, 'version' => $version, 'content_hash' => $hash, 'name' => $name, 'language' => Str::limit($language, 16, ''), 'category' => $category, 'status' => $status, 'components' => $components, 'captured_at' => Carbon::now()]);
            } else {
                $template->forceFill(['last_synced_at' => now()])->save();
            }
            $submissionHash = hash('sha256', json_encode([$name, Str::limit($language, 16, ''), $category, $components], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
            $submissions = WhatsAppTemplateSubmission::query()
                ->where('connector_id', $connector->id)
                ->where('name', $name)
                ->where('language', Str::limit($language, 16, ''))
                ->where('review_status', 'approved')
                ->get();
            foreach ($submissions as $submission) {
                $matchesSubmission = hash_equals($submission->content_hash, $submissionHash);
                $submission->forceFill([
                    'whatsapp_template_id' => $matchesSubmission ? $template->id : null,
                    'provider_status' => $matchesSubmission ? $status->value : 'content_mismatch',
                ])->save();
            }
            $counts[$created ? 'created' : ($changed ? 'updated' : 'unchanged')]++;

            return true;
        });
    }

    private function templateStatus(?string $value): WhatsAppTemplateStatus
    {
        return match (strtolower((string) $value)) {
            'approved', 'active' => WhatsAppTemplateStatus::Approved,
            'pending', 'in_review', 'submitted' => WhatsAppTemplateStatus::Pending,
            'rejected', 'disabled' => WhatsAppTemplateStatus::Rejected,
            'draft' => WhatsAppTemplateStatus::Draft,
            default => WhatsAppTemplateStatus::Unknown,
        };
    }

    /** @param array<string,mixed> $item @param list<string> $keys */
    private function scalar(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($item[$key]) && is_scalar($item[$key]) && trim((string) $item[$key]) !== '') {
                return trim((string) $item[$key]);
            }
        }

        return null;
    }
}
