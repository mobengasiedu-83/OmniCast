<?php

namespace App\Services\WhatsApp;

use App\Models\Client;
use App\Models\User;
use App\Models\WhatsAppAuditLog;
use Illuminate\Database\Eloquent\Model;

/** Persists safe, append-only audit records for WhatsApp administration. */
class WhatsAppAuditLogger
{
    /** @param array<string, mixed> $properties */
    public function record(
        string $action,
        ?User $actor,
        ?Client $client,
        ?Model $subject,
        array $properties = [],
    ): WhatsAppAuditLog {
        return WhatsAppAuditLog::query()->create([
            'client_id' => $client?->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $this->redact($properties),
            'occurred_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $properties
     * @return array<string, mixed>
     */
    protected function redact(array $properties): array
    {
        $safe = [];

        foreach ($properties as $key => $value) {
            if (preg_match('/token|secret|password|credential|authorization|api.?key|otp|client.?id/i', (string) $key) === 1) {
                $safe[$key] = '[REDACTED]';

                continue;
            }

            $safe[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $safe;
    }
}
