<?php

namespace App\Services;

use App\Enums\Permission;
use App\Enums\SenderIdChannel;
use App\Enums\SenderIdStatus;
use App\Mail\SenderIdRequestedMail;
use App\Models\CallerId;
use App\Models\SenderId;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Moves sender ID requests through the request → review → approval cycle and
 * notifies the platform team whenever a request needs attention.
 */
class SenderIdWorkflow
{
    /**
     * Submit a brand new sender ID request for review.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function request(array $attributes, User $requester): SenderId
    {
        $senderId = SenderId::query()->create([
            ...$attributes,
            'status' => SenderIdStatus::Pending,
            'requested_at' => now(),
            'created_by' => $requester->id,
        ]);

        $this->notifyReviewers($senderId, $requester);

        return $senderId;
    }

    /**
     * Apply the client's changes and send the request back to the review queue.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function resubmit(SenderId $senderId, array $attributes, User $requester): SenderId
    {
        $senderId->forceFill([
            ...$attributes,
            'status' => SenderIdStatus::Pending,
            'submitted_at' => null,
            'approved_at' => null,
            'approved_by' => null,
            'rejection_reason' => null,
        ])->save();

        $this->notifyReviewers($senderId->refresh(), $requester);

        return $senderId;
    }

    /**
     * Approve a request, provisioning the caller ID when the channel is voice.
     */
    public function approve(SenderId $senderId, User $reviewer): SenderId
    {
        $senderId->forceFill([
            'status' => SenderIdStatus::Approved,
            'submitted_at' => $senderId->submitted_at ?? now(),
            'approved_at' => now(),
            'approved_by' => $reviewer->id,
            'rejection_reason' => null,
        ])->save();

        $this->provisionVoiceCallerId($senderId, $reviewer);

        return $senderId;
    }

    /**
     * Reject a request with a reason the client can act on.
     */
    public function reject(SenderId $senderId, User $reviewer, string $reason): SenderId
    {
        $senderId->forceFill([
            'status' => SenderIdStatus::Rejected,
            'rejection_reason' => $reason,
            'approved_at' => null,
            'approved_by' => $reviewer->id,
        ])->save();

        return $senderId;
    }

    /**
     * The platform team members that receive sender ID request emails.
     *
     * @return Collection<int, User>
     */
    public function reviewers(): Collection
    {
        return User::query()
            ->whereNull('client_id')
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user): bool => $user->can(Permission::ApproveSenderIds->value))
            ->values();
    }

    /**
     * Email every platform reviewer about a request awaiting a decision.
     */
    public function notifyReviewers(SenderId $senderId, ?User $requester = null): int
    {
        $reviewers = $this->reviewers();

        if ($reviewers->isEmpty()) {
            Log::warning('Sender ID requested but no platform reviewer could be emailed.', [
                'sender_id' => $senderId->id,
                'client_id' => $senderId->client_id,
            ]);

            return 0;
        }

        foreach ($reviewers as $reviewer) {
            Mail::to($reviewer->email)->queue(new SenderIdRequestedMail($senderId, $requester));
        }

        return $reviewers->count();
    }

    /**
     * Make an approved voice sender number selectable for voice campaigns.
     */
    protected function provisionVoiceCallerId(SenderId $senderId, User $reviewer): void
    {
        if ($senderId->channel !== SenderIdChannel::Voice) {
            return;
        }

        CallerId::query()->updateOrCreate(
            [
                'client_id' => $senderId->client_id,
                'number' => $senderId->name,
            ],
            [
                'label' => Str::limit($senderId->purpose ?? 'Voice sender ID', 60, ''),
                'status' => SenderIdStatus::Approved,
                'requested_at' => $senderId->requested_at,
                'approved_at' => $senderId->approved_at,
                'approved_by' => $reviewer->id,
                'rejection_reason' => null,
                'created_by' => $senderId->created_by,
            ],
        );
    }
}
