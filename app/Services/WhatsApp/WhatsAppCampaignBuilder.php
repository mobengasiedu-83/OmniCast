<?php

namespace App\Services\WhatsApp;

use App\Enums\WhatsAppApprovalStatus;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactSegment;
use App\Models\ContactSuppression;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppClientConnector;
use App\Models\WhatsAppConnector;
use App\Models\WhatsAppContactConsent;
use App\Models\WhatsAppTemplate;
use App\Services\ContactSegmentQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WhatsAppCampaignBuilder
{
    public function __construct(private ContactSegmentQuery $segments, private WhatsAppPricingService $pricing) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $user): WhatsAppCampaign
    {
        $clientId = (int) $user->client_id;
        $assignment = WhatsAppClientConnector::query()->with('connector')->where('client_id', $clientId)
            ->where('connector_id', $data['connector_id'])->where('enabled', true)
            ->where('approval_status', WhatsAppApprovalStatus::Approved)->firstOrFail();
        $template = WhatsAppTemplate::query()->where('connector_id', $assignment->connector_id)->whereKey($data['template_id'])->where('status', 'approved')->firstOrFail();

        if (($data['contact_list_id'] ?? null) && ($data['contact_segment_id'] ?? null)) {
            throw ValidationException::withMessages(['audience' => 'Choose a contact list or a saved segment, not both.']);
        }

        return DB::transaction(function () use ($data, $user, $clientId, $assignment, $template): WhatsAppCampaign {
            $campaign = WhatsAppCampaign::query()->create([
                'client_id' => $clientId,
                'connector_id' => $assignment->connector_id,
                'template_id' => $template->id,
                'name' => $data['name'],
                'status' => 'draft',
                'contact_list_id' => $data['contact_list_id'] ?? null,
                'contact_segment_id' => $data['contact_segment_id'] ?? null,
                'variable_mapping' => $data['variable_mapping'] ?? [],
                'media_asset_id' => $data['media_asset_id'] ?? null,
                'schedule_type' => $data['schedule_type'],
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'timezone' => $data['timezone'],
                'created_by' => $user->id,
            ]);

            $audience = $this->audience($clientId, $data);
            $audienceVolume = $audience->count();
            foreach ($audience as $contact) {
                $this->snapshot($campaign, $contact, $template, $assignment->connector, $audienceVolume);
            }

            $eligible = $campaign->recipients()->where('eligibility', 'eligible')->count();
            $estimate = (float) $campaign->recipients()->where('eligibility', 'eligible')->sum('unit_price');
            $priceSnapshots = $campaign->recipients()->where('eligibility', 'eligible')->get()->pluck('unit_price_snapshot')->unique()->values()->all();
            $unpricedRecipients = $campaign->recipients()->get()->filter(fn (WhatsAppCampaignRecipient $recipient): bool => collect($recipient->errors_json)->contains(fn (string $error): bool => str_contains($error, 'No approved effective rate')))->count();
            $campaign->forceFill([
                'estimated_recipients' => $eligible,
                'estimated_cost' => $eligible === 0 ? '0.0000' : number_format($estimate, 4, '.', ''),
                'estimate_currency' => 'GHS',
                'price_snapshot' => ['currency' => 'GHS', 'rates' => $priceSnapshots, 'captured_at' => now()->toIso8601String()],
                'estimate_disclaimer' => $unpricedRecipients > 0
                    ? ['status' => 'partial_unpriced', 'unpriced_recipients' => $unpricedRecipients, 'message' => 'Some recipients have no approved effective rate and cannot be sent.']
                    : ['status' => 'approved_rate_estimate', 'message' => 'Estimate uses approved effective WhatsApp rate inputs. Final charges require confirmed billable delivery evidence.'],
            ])->save();

            $campaign->forceFill(['dispatch_uuid' => (string) Str::uuid()])->save();

            return $campaign->load('recipients');
        });
    }

    public function refreshEligibility(WhatsAppCampaign $campaign): int
    {
        $campaign->loadMissing(['template', 'connector']);
        $eligible = 0;
        foreach ($campaign->recipients()->where('eligibility', 'eligible')->orderBy('id')->get() as $recipient) {
            $contact = $recipient->contact;
            $reason = $this->currentIneligibility($campaign, $recipient->consent_id, $contact, $recipient->phone, $campaign->template->category);
            $recipient->forceFill([
                'consent_checked_at' => now(),
                'status' => $reason === null ? 'ready' : 'ineligible',
                'errors_json' => $reason === null ? [] : [$reason],
            ])->save();
            if ($reason === null) {
                $eligible++;
            }
        }

        return $eligible;
    }

    public function currentIneligibility(WhatsAppCampaign $campaign, ?int $consentId, ?Contact $contact, string $phone, ?string $category): ?string
    {
        if ($contact === null || (int) $contact->client_id !== (int) $campaign->client_id) {
            return 'Contact is no longer available in this client account.';
        }
        if ($contact->dnd || $contact->is_do_not_contact) {
            return 'Contact is marked do-not-contact.';
        }
        $purpose = strtolower((string) $category);
        $purpose = in_array($purpose, ['marketing', 'utility', 'authentication'], true) ? $purpose : 'marketing';
        $consent = $consentId === null ? null : WhatsAppContactConsent::query()->whereKey($consentId)
            ->where('client_id', $campaign->client_id)->where('contact_id', $contact->id)->where('purpose', $purpose)->whereNull('revoked_at')->first();
        if ($consent === null) {
            return 'Active WhatsApp consent is required.';
        }
        if ($contact->phone !== $phone) {
            return 'Contact phone number changed after the audience snapshot was created.';
        }
        if (ContactSuppression::query()->where('is_active', true)->where(function (Builder $query) use ($campaign): void {
            $query->whereNull('client_id')->orWhere('client_id', $campaign->client_id);
        })->whereIn('channel', ['whatsapp', ContactSuppression::ALL_CHANNELS])->where('identifier', $phone)->exists()) {
            return 'Phone number is suppressed.';
        }

        return null;
    }

    /** @param array<string, mixed> $data @return Collection<int, Contact> */
    private function audience(int $clientId, array $data): Collection
    {
        $query = Contact::query()->where('client_id', $clientId);
        if (isset($data['contact_list_id'])) {
            $list = ContactList::query()->where('client_id', $clientId)->findOrFail($data['contact_list_id']);
            $query->whereHas('contactLists', fn (Builder $lists) => $lists->whereKey($list->id));
        }
        if (isset($data['contact_segment_id'])) {
            $segment = ContactSegment::query()->where('client_id', $clientId)->findOrFail($data['contact_segment_id']);
            $query = $this->segments->apply($query, $segment->filters, $clientId);
        }

        return $query->with('tags:id,name')->orderBy('id')->get();
    }

    private function snapshot(WhatsAppCampaign $campaign, Contact $contact, WhatsAppTemplate $template, WhatsAppConnector $connector, int $audienceVolume): void
    {
        $number = $this->normalizePhone($contact->phone);
        $reasons = [];
        $price = null;
        $priceSnapshot = null;
        $purpose = strtolower((string) ($template->category ?: 'marketing'));
        $purpose = in_array($purpose, ['marketing', 'utility', 'authentication'], true) ? $purpose : 'marketing';
        $consent = WhatsAppContactConsent::query()->where('client_id', $campaign->client_id)->where('contact_id', $contact->id)
            ->where('purpose', $purpose)->whereNull('revoked_at')->latest('granted_at')->first();
        if (! $consent) {
            $reasons[] = 'No active consent record for the template category.';
        }
        if ($contact->dnd || $contact->is_do_not_contact) {
            $reasons[] = 'Contact is marked do-not-contact.';
        }
        if ($number === null) {
            $reasons[] = 'Phone number is not valid E.164.';
        }
        if ($number !== null) {
            $market = $this->pricing->marketFor($number);
            $category = $this->pricing->categoryFor($template->category);
            $rate = $this->pricing->resolve($campaign->client, $market, $category, $audienceVolume);
            if ($rate === null) {
                $reasons[] = "No approved effective rate for {$market}/{$category}.";
            } else {
                $price = (float) $rate->selling_price;
                $priceSnapshot = $rate->inputsSnapshot();
            }
        }
        if ($number !== null && ContactSuppression::query()->where('is_active', true)->where(function (Builder $query) use ($campaign): void {
            $query->whereNull('client_id')->orWhere('client_id', $campaign->client_id);
        })->whereIn('channel', ['whatsapp', ContactSuppression::ALL_CHANNELS])->where('identifier', $number)->exists()) {
            $reasons[] = 'Phone number is suppressed.';
        }
        $variables = [];
        foreach ((array) $campaign->variable_mapping as $placeholder => $field) {
            $variables[(string) $placeholder] = data_get($contact, (string) $field, data_get($contact->custom_fields ?? [], (string) $field));
        }
        foreach ($this->requiredVariables($template->components ?? []) as $placeholder) {
            if (! filled($variables[$placeholder] ?? null)) {
                $reasons[] = "Required template variable {$placeholder} is missing.";
            }
        }
        $eligible = $reasons === [];
        $snapshot = [
            'contact_id' => $contact->id,
            'phone' => $number ?? $contact->phone,
            'name' => $contact->full_name,
            'custom_fields' => $contact->custom_fields,
            'tags' => $contact->tags->pluck('name')->all(),
            'template_id' => $template->id,
            'template_version_hash' => $template->content_hash,
            'connector_id' => $connector->id,
            'consent_id' => $consent?->id,
            'consent_granted_at' => $consent?->granted_at?->toIso8601String(),
            'price' => $priceSnapshot,
            'captured_at' => now()->toIso8601String(),
        ];
        WhatsAppCampaignRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'consent_id' => $consent?->id,
            'phone' => $number ?? $contact->phone,
            'variables_json' => $variables,
            'eligibility' => $eligible ? 'eligible' : 'ineligible',
            'errors_json' => $reasons,
            'snapshot_json' => $snapshot,
            'consent_checked_at' => now(),
            'unit_price' => $price,
            'unit_price_snapshot' => $priceSnapshot,
        ]);
    }

    /** @param array<int, mixed> $components @return list<string> */
    private function requiredVariables(array $components): array
    {
        $variables = [];
        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }
            preg_match_all('/\\{\\{(\\d+)\\}\\}/', (string) ($component['text'] ?? ''), $matches);
            foreach ($matches[1] as $placeholder) {
                $variables[] = $placeholder;
            }
        }

        return array_values(array_unique($variables));
    }

    private function normalizePhone(string $phone): ?string
    {
        $number = preg_replace('/[\\s().-]+/', '', trim($phone));
        if ($number !== null && preg_match('/^\\+[1-9]\\d{7,14}$/', $number) === 1) {
            return $number;
        }

        return null;
    }
}
