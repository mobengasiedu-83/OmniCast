<?php

namespace App\Services\WhatsApp;

use App\Enums\WhatsAppApprovalStatus;
use App\Enums\WhatsAppConnectorStatus;
use App\Enums\WhatsAppProviderStatus;
use App\Enums\WhatsAppTemplateStatus;
use App\Models\Client;
use App\Models\User;
use App\Models\WhatsAppClientConnector;
use App\Models\WhatsAppConnector;
use App\Models\WhatsAppProvider;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppTemplateVersion;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class MetaWhatsAppEmbeddedSignup
{
    /** @return array{connector: WhatsAppConnector, templates_synced: int, templates_sync_failed: bool} */
    public function complete(Client $client, User $user, array $data): array
    {
        $appId = config('whatsapp.meta.app_id');
        $appSecret = config('whatsapp.meta.app_secret');
        $graphVersion = config('whatsapp.meta.graph_version');
        if (! config('whatsapp.meta.onboarding_enabled') || ! filled($appId) || ! filled($appSecret) || ! filled(config('whatsapp.meta.embedded_signup_config_id'))) {
            throw ValidationException::withMessages(['meta' => 'Meta account connection is not configured. Contact your platform administrator.']);
        }

        try {
            $tokenResponse = $this->graph()->get("/{$graphVersion}/oauth/access_token", [
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'code' => $data['code'],
            ]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['meta' => 'Meta could not be reached. Please try the connection again.']);
        }
        $token = $tokenResponse->json('access_token');
        if (! $tokenResponse->successful() || ! is_string($token) || $token === '') {
            throw ValidationException::withMessages(['meta' => 'Meta could not verify the signup. Please reconnect your account.']);
        }

        $wabaId = $data['waba_id'];
        $phoneNumberId = $data['phone_number_id'];
        try {
            $wabaResponse = $this->graph($token)->get("/{$graphVersion}/{$wabaId}", ['fields' => 'id,name']);
            $phoneResponse = $this->graph($token)->get("/{$graphVersion}/{$phoneNumberId}", ['fields' => 'id,display_phone_number,verified_name,quality_rating,code_verification_status']);
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['meta' => 'Meta could not be reached while verifying the selected account. Please try again.']);
        }
        if (! $wabaResponse->successful() || (string) $wabaResponse->json('id') !== $wabaId
            || ! $phoneResponse->successful() || (string) $phoneResponse->json('id') !== $phoneNumberId) {
            throw ValidationException::withMessages(['meta' => 'Meta did not grant access to the selected WhatsApp Business Account and phone number.']);
        }

        try {
            $subscription = $this->graph($token)->post("/{$graphVersion}/{$wabaId}/subscribed_apps");
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['meta' => 'Meta could not enable delivery notifications. Please try again.']);
        }
        if (! $subscription->successful()) {
            throw ValidationException::withMessages(['meta' => 'Meta connected the account but could not enable delivery notifications. Please try again or contact support.']);
        }

        $provider = WhatsAppProvider::query()->firstOrCreate(
            ['name' => 'Meta Cloud API'],
            [
                'driver' => 'meta_cloud',
                'status' => WhatsAppProviderStatus::Connected,
                'is_enabled' => true,
                'connect_timeout' => 5,
                'request_timeout' => 20,
            ],
        );
        if ($provider->driver !== 'meta_cloud') {
            throw ValidationException::withMessages(['meta' => 'Meta Cloud API provider configuration conflicts with an existing provider. Contact your platform administrator.']);
        }

        $expiresIn = $tokenResponse->json('expires_in');
        $tokenExpiresAt = is_numeric($expiresIn) && (int) $expiresIn > 0 ? now()->addSeconds((int) $expiresIn) : null;
        $connector = DB::transaction(function () use ($client, $user, $provider, $wabaId, $phoneNumberId, $token, $tokenExpiresAt, $data, $phoneResponse, $wabaResponse): WhatsAppConnector {
            $connector = WhatsAppConnector::query()->firstOrNew([
                'provider_id' => $provider->id,
                'external_id' => $phoneNumberId,
            ]);
            if ($connector->exists && $connector->client_id !== null && (int) $connector->client_id !== (int) $client->id) {
                throw ValidationException::withMessages(['meta' => 'This Meta phone number is already connected to another OmniCast client.']);
            }
            $connector->fill([
                'client_id' => $client->id,
                'business_id' => $data['business_id'] ?? null,
                'waba_id' => $wabaId,
                'access_token' => $token,
                'token_expires_at' => $tokenExpiresAt,
                'connector_name' => $phoneResponse->json('verified_name') ?: $wabaResponse->json('name') ?: 'Meta WhatsApp sender',
                'sender_number' => $phoneResponse->json('display_phone_number'),
                'status' => WhatsAppConnectorStatus::Available,
                'last_synced_at' => now(),
            ])->save();

            WhatsAppClientConnector::query()->updateOrCreate(
                ['client_id' => $client->id, 'connector_id' => $connector->id],
                [
                    'enabled' => true,
                    'approval_status' => WhatsAppApprovalStatus::Approved,
                    'approved_by' => $user->id,
                    'created_by' => $user->id,
                    'approved_at' => now(),
                ],
            );

            return $connector;
        });

        [$templateCount, $syncFailed] = $this->syncTemplates($connector, $token, $wabaId);

        return ['connector' => $connector, 'templates_synced' => $templateCount, 'templates_sync_failed' => $syncFailed];
    }

    /** @return array{int, bool} */
    private function syncTemplates(WhatsAppConnector $connector, string $token, string $wabaId): array
    {
        try {
            $response = $this->graph($token)->get('/'.config('whatsapp.meta.graph_version')."/{$wabaId}/message_templates", [
                'fields' => 'id,name,language,category,status,components',
                'limit' => 100,
            ]);
        } catch (ConnectionException) {
            return [0, true];
        }
        if (! $response->successful() || ! is_array($response->json('data'))) {
            return [0, true];
        }

        $count = 0;
        foreach ($response->json('data') as $item) {
            if (! is_array($item) || ! isset($item['id'], $item['name'], $item['language'])) {
                continue;
            }
            $status = match (strtoupper((string) ($item['status'] ?? ''))) {
                'APPROVED' => WhatsAppTemplateStatus::Approved,
                'PENDING', 'IN_APPEAL' => WhatsAppTemplateStatus::Pending,
                'REJECTED' => WhatsAppTemplateStatus::Rejected,
                default => WhatsAppTemplateStatus::Unknown,
            };
            $components = is_array($item['components'] ?? null) ? $item['components'] : [];
            $hash = hash('sha256', json_encode([$item['name'], $item['language'], $item['category'] ?? null, $status->value, $components], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
            $template = WhatsAppTemplate::query()->firstOrNew(['connector_id' => $connector->id, 'meta_template_id' => (string) $item['id']]);
            $changed = ! $template->exists || ! hash_equals((string) $template->content_hash, $hash);
            $template->fill([
                'name' => $item['name'],
                'language' => $item['language'],
                'category' => $item['category'] ?? null,
                'status' => $status,
                'components' => $components,
                'content_hash' => $hash,
                'last_synced_at' => now(),
            ])->save();
            if ($changed && ! $template->versions()->where('content_hash', $hash)->exists()) {
                WhatsAppTemplateVersion::query()->create([
                    'template_id' => $template->id,
                    'version' => ((int) $template->versions()->max('version')) + 1,
                    'content_hash' => $hash,
                    'name' => $template->name,
                    'language' => $template->language,
                    'category' => $template->category,
                    'status' => $template->status->value,
                    'components' => $components,
                    'captured_at' => now(),
                ]);
            }
            $count++;
        }

        return [$count, false];
    }

    private function graph(?string $token = null): PendingRequest
    {
        $request = Http::baseUrl('https://graph.facebook.com')->acceptJson()->connectTimeout(5)->timeout(20);
        if ($token !== null) {
            $request = $request->withToken($token);
        }

        return $request;
    }
}
