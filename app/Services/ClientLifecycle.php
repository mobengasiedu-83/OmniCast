<?php

namespace App\Services;

use App\Enums\ClientStatus;
use App\Exceptions\InvalidClientTransition;
use App\Mail\ClientActivated;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Moves a client tenant through its lifecycle and records why it moved.
 *
 * Every transition is written to the tenant row (status, reason and timestamps)
 * and to the activity log so a suspension can always be explained later.
 */
class ClientLifecycle
{
    /**
     * Move the tenant to the given state.
     */
    public function transition(
        Client $client,
        ClientStatus $target,
        ?string $reason = null,
        ?User $actor = null,
    ): Client {
        $from = $client->status;

        if (! $from->canTransitionTo($target)) {
            throw InvalidClientTransition::between($client, $from, $target);
        }

        DB::transaction(function () use ($client, $from, $target, $reason, $actor): void {
            $client->forceFill([
                'status' => $target,
                'status_reason' => $reason,
                'status_changed_at' => now(),
                'activated_at' => $target === ClientStatus::Active ? now() : $client->activated_at,
                'suspended_at' => $target === ClientStatus::Suspended ? now() : null,
                'archived_at' => $target === ClientStatus::Archived ? now() : null,
            ])->save();

            activity()
                ->performedOn($client)
                ->causedBy($actor)
                ->withProperties([
                    'from' => $from->value,
                    'to' => $target->value,
                    'reason' => $reason,
                ])
                ->log('changed client status');
        });

        $client->refresh();

        if ($target === ClientStatus::Active && $from !== ClientStatus::Active && filled($client->email)) {
            Mail::to($client->email)->queue(new ClientActivated($client));
        }

        return $client;
    }

    /**
     * Sign off a pending tenant so it may broadcast.
     */
    public function activate(Client $client, ?User $actor = null): Client
    {
        return $this->transition($client, ClientStatus::Active, null, $actor);
    }

    /**
     * Put an active tenant on hold with a reason.
     */
    public function suspend(Client $client, string $reason, ?User $actor = null): Client
    {
        return $this->transition($client, ClientStatus::Suspended, $reason, $actor);
    }

    /**
     * Bring a suspended tenant back into service.
     */
    public function reactivate(Client $client, ?User $actor = null): Client
    {
        return $this->transition($client, ClientStatus::Active, null, $actor);
    }

    /**
     * Close the tenant permanently.
     */
    public function archive(Client $client, string $reason, ?User $actor = null): Client
    {
        return $this->transition($client, ClientStatus::Archived, $reason, $actor);
    }
}
