<?php

namespace App\Console\Commands;

use App\Enums\PrivacyRetentionCategory;
use App\Models\WhatsAppMessageAttempt;
use App\Models\WhatsAppProviderEvent;
use App\Services\Privacy\PrivacyRetentionPolicyService;
use Illuminate\Console\Command;

class PruneWhatsAppSensitivePayloads extends Command
{
    protected $signature = 'whatsapp:prune-sensitive-payloads';

    protected $description = 'Redact aged WhatsApp payload copies when a retention policy is approved';

    public function handle(PrivacyRetentionPolicyService $retention): int
    {
        $days = $retention->approvedDays(PrivacyRetentionCategory::WhatsAppSensitivePayloads);
        if ($days === null) {
            $this->info('WhatsApp payload redaction is disabled until a retention period is approved.');

            return self::SUCCESS;
        }
        $cutoff = now()->subDays($days);
        $attempts = 0;
        WhatsAppMessageAttempt::query()->where('created_at', '<=', $cutoff)->where(function ($query): void {
            $query->whereNotNull('request_payload_redacted')->orWhereNotNull('response_payload_redacted')->orWhereNotNull('error_message');
        })->orderBy('id')->chunkById(500, function ($batch) use (&$attempts): void {
            $attempts += WhatsAppMessageAttempt::query()->whereIn('id', $batch->modelKeys())->update([
                'request_payload_redacted' => null,
                'response_payload_redacted' => null,
                'error_message' => null,
                'updated_at' => now(),
            ]);
        });
        $events = 0;
        WhatsAppProviderEvent::query()->where('created_at', '<=', $cutoff)->whereNotNull('payload_redacted')->orderBy('id')->chunkById(500, function ($batch) use (&$events): void {
            $events += WhatsAppProviderEvent::query()->whereIn('id', $batch->modelKeys())->update(['payload_redacted' => null, 'updated_at' => now()]);
        });

        $this->info("Redacted payload copies for {$attempts} message attempt(s) and {$events} provider event(s).");

        return self::SUCCESS;
    }
}
