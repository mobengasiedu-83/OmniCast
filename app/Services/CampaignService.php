<?php

namespace App\Services;

use App\Enums\AutomationTrigger;
use App\Enums\CampaignAudioStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\ReservationStatus;
use App\Enums\WebhookEvent;
use App\Exceptions\InsufficientWalletBalance;
use App\Jobs\DispatchCampaignJob;
use App\Models\BillingReservation;
use App\Models\Campaign;
use App\Models\User;
use App\Services\Automation\AutomationEngine;
use App\Services\Delivery\QueueTopology;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Drives a campaign through its lifecycle: draft, review, approval and launch.
 */
class CampaignService
{
    public function __construct(
        protected CampaignCostEstimator $estimator,
        protected CampaignAudienceBuilder $audience,
        protected WalletService $wallet,
        protected PlatformConfiguration $configuration,
        protected ClientQuotaService $quota,
        protected CampaignVersionRecorder $versions,
        protected CampaignReadiness $readiness,
        protected QueueTopology $queues,
        protected RateResolver $rates,
        protected WebhookDispatcher $webhooks,
        protected AutomationEngine $automations,
    ) {}

    /**
     * Create a draft campaign and price it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $user): Campaign
    {
        $attributes['idempotency_key'] ??= (string) Str::uuid();

        $campaign = Campaign::query()->firstOrCreate(
            [
                'client_id' => $attributes['client_id'],
                'idempotency_key' => $attributes['idempotency_key'],
            ],
            [
                ...$attributes,
                'reference' => $attributes['reference'] ?? $this->generateReference(),
                'status' => CampaignStatus::Draft,
                'created_by' => $user->id,
            ],
        );

        if (! $campaign->wasRecentlyCreated) {
            return $campaign;
        }

        $campaign = $this->refreshEstimate($campaign);

        $this->webhooks->campaign(WebhookEvent::CampaignCreated, $campaign);

        return $campaign;
    }

    /**
     * Update an editable campaign and reprice it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Campaign $campaign, array $attributes): Campaign
    {
        unset($attributes['idempotency_key']);

        $campaign->fill($attributes)->save();

        return $this->refreshEstimate($campaign);
    }

    /**
     * Copy a campaign into a fresh draft the tenant can edit and submit again.
     */
    public function duplicate(Campaign $campaign, User $user): Campaign
    {
        $copy = $campaign->replicate([
            'reference', 'status', 'idempotency_key', 'current_version', 'duplicated_from_id',
            'reserved_amount', 'actual_cost', 'submitted_at', 'approved_at', 'approved_by',
            'rejected_at', 'rejection_reason', 'launched_at', 'completed_at', 'created_by',
        ]);

        $copy->forceFill([
            'reference' => $this->generateReference(),
            'name' => Str::limit($campaign->name, 113, '').' (copy)',
            'status' => CampaignStatus::Draft,
            'idempotency_key' => (string) Str::uuid(),
            'current_version' => 0,
            'duplicated_from_id' => $campaign->id,
            'created_by' => $user->id,
            'reserved_amount' => 0,
            'actual_cost' => 0,
            'submitted_at' => null,
            'approved_at' => null,
            'approved_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'launched_at' => null,
            'completed_at' => null,
        ])->save();

        return $this->refreshEstimate($copy);
    }

    /**
     * Recalculate the audience size, SMS parts and expected spend.
     */
    public function refreshEstimate(Campaign $campaign): Campaign
    {
        $campaign->loadMissing('client');

        $estimate = $this->estimator->estimate(
            $campaign->client,
            $campaign->channel,
            $campaign->sms_message,
            $this->audience->count($campaign),
        );

        $campaign->forceFill([
            'estimated_recipients' => $estimate['recipients'],
            'estimated_sms_parts' => $estimate['sms_parts'],
            'estimated_cost' => $estimate['estimated_cost'],
            'sms_encoding' => $estimate['encoding'],
        ])->save();

        return $campaign;
    }

    /**
     * Snapshot the audience, hold the funds and either approve the campaign
     * straight away or send it to a reviewer.
     */
    public function submit(Campaign $campaign, User $user): Campaign
    {
        $campaign = DB::transaction(function () use ($campaign, $user): Campaign {
            $campaign = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $this->ensureChannelsEnabled($campaign);
            $this->readiness->assertReady($campaign, includeAudio: false);
            $this->audience->build($campaign);
            $this->refreshEstimate($campaign);
            $this->quota->assertAvailable($campaign);
            $this->warnAboutUnpricedServices($campaign);

            $this->holdFunds($campaign, (float) $campaign->estimated_cost, $user);

            $campaign->forceFill([
                'status' => CampaignStatus::PendingApproval,
                'submitted_at' => now(),
                'approved_at' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                ...$this->pendingAudioReview(),
            ])->save();

            if ($this->shouldAutoApprove($campaign)) {
                return $this->autoApprove($campaign, $user);
            }

            $this->versions->record($campaign, CampaignStatus::PendingApproval, $user);

            return $campaign;
        });

        $this->webhooks->campaign(WebhookEvent::CampaignSubmitted, $campaign);

        if ($campaign->status === CampaignStatus::Approved) {
            $this->webhooks->campaign(WebhookEvent::CampaignApproved, $campaign);
        }

        return $campaign;
    }

    /**
     * Approve a campaign that is waiting for review.
     */
    public function approve(Campaign $campaign, User $user): Campaign
    {
        $campaign = DB::transaction(function () use ($campaign, $user): Campaign {
            $campaign = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);

            if (! $campaign->status->canBeApproved()) {
                throw ValidationException::withMessages([
                    'campaign' => 'Only a campaign waiting for approval can be approved.',
                ]);
            }

            $this->readiness->assertReady($campaign);

            $campaign->forceFill([
                'status' => CampaignStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $user->id,
            ])->save();

            $this->versions->record($campaign, CampaignStatus::Approved, $user);

            return $campaign;
        });

        $this->webhooks->campaign(WebhookEvent::CampaignApproved, $campaign);

        return $campaign;
    }

    /**
     * Clear the voice audio for delivery after a reviewer approves it.
     *
     * When campaigns approve themselves, clearing the last gate is what moves
     * the campaign from the review queue to the launch queue.
     */
    public function approveAudio(Campaign $campaign, User $user): Campaign
    {
        $campaign = DB::transaction(function () use ($campaign, $user): Campaign {
            $campaign = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $this->assertAudioReviewable($campaign);

            $campaign->forceFill([
                'audio_status' => CampaignAudioStatus::Approved,
                'audio_reviewed_at' => now(),
                'audio_reviewed_by' => $user->id,
                'audio_rejection_reason' => null,
            ])->save();

            if ($campaign->status->canBeApproved() && $this->shouldAutoApprove($campaign)) {
                return $this->autoApprove($campaign, $user);
            }

            return $campaign;
        });

        if ($campaign->status === CampaignStatus::Approved) {
            $this->webhooks->campaign(WebhookEvent::CampaignApproved, $campaign);
        }

        return $campaign;
    }

    /**
     * Refuse the audio and explain what the tenant has to change.
     */
    public function rejectAudio(Campaign $campaign, User $user, string $reason): Campaign
    {
        $this->assertAudioReviewable($campaign);

        $campaign->forceFill([
            'audio_status' => CampaignAudioStatus::Rejected,
            'audio_reviewed_at' => now(),
            'audio_reviewed_by' => $user->id,
            'audio_rejection_reason' => $reason,
        ])->save();

        return $campaign;
    }

    /**
     * Reject a campaign and give the held funds back.
     */
    public function reject(Campaign $campaign, User $user, string $reason): Campaign
    {
        $campaign->forceFill([
            'status' => CampaignStatus::Rejected,
            'rejected_at' => now(),
            'approved_by' => $user->id,
            'rejection_reason' => $reason,
        ])->save();

        $this->releaseReservation($campaign, 'Funds released after campaign rejection');

        $this->webhooks->campaign(WebhookEvent::CampaignRejected, $campaign);

        return $campaign;
    }

    /**
     * Launch an approved campaign now or schedule it for later.
     */
    public function launch(Campaign $campaign): Campaign
    {
        $this->ensureChannelsEnabled($campaign);
        $this->readiness->assertReady($campaign);
        $this->versions->assertMatchesApprovedSnapshot($campaign);

        $campaign = DB::transaction(function () use ($campaign): Campaign {
            $campaign = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);

            if (! $campaign->status->canBeScheduled()) {
                throw ValidationException::withMessages([
                    'campaign' => 'Only an approved campaign can be launched.',
                ]);
            }

            $scheduled = $campaign->schedule_type === 'scheduled'
                && $campaign->scheduled_at !== null
                && $campaign->scheduled_at->isFuture();

            $campaign->forceFill([
                'status' => $scheduled ? CampaignStatus::Scheduled : CampaignStatus::Running,
                'launched_at' => $scheduled ? null : now(),
            ])->save();

            return $campaign;
        });

        $this->holdFunds($campaign, $this->outstandingEstimate($campaign));

        $job = DispatchCampaignJob::dispatch($campaign->id)
            ->onQueue($this->queues->queueForCampaign($campaign));

        if ($campaign->status === CampaignStatus::Scheduled) {
            $job->delay($campaign->scheduled_at ?? now());
        }

        $this->webhooks->campaign(WebhookEvent::CampaignLaunched, $campaign);

        $this->automations->emit(AutomationTrigger::CampaignLaunched, null, [
            'client_id' => $campaign->client_id,
            'campaign_id' => $campaign->id,
            'campaign' => $campaign->name,
        ]);

        return $campaign;
    }

    /**
     * Cancel a campaign and release whatever is still held for it.
     */
    public function cancel(Campaign $campaign): Campaign
    {
        $campaign->forceFill([
            'status' => CampaignStatus::Cancelled,
        ])->save();

        $this->releaseReservation($campaign, 'Funds released after campaign cancellation');

        $this->webhooks->campaign(WebhookEvent::CampaignCancelled, $campaign);

        return $campaign;
    }

    /**
     * Hold the money the campaign is about to spend.
     *
     * The deduction starts here: the wallet keeps the estimate held until the
     * dispatcher debits each delivered unit. A tenant who cannot cover the
     * estimate is told so instead of receiving a failed campaign.
     */
    private function holdFunds(Campaign $campaign, float $amount, ?User $user = null): void
    {
        try {
            $this->wallet->startBilling($campaign, $amount, $user);
        } catch (InsufficientWalletBalance) {
            throw ValidationException::withMessages([
                'campaign' => 'The wallet balance cannot cover the estimated GHS '.number_format($amount, 2).'. Top up the wallet and try again.',
            ]);
        }
    }

    /**
     * Tell the platform team when a service has no price to bill it at.
     *
     * The price list lives on the client card and, failing that, on the platform
     * default cards, so an unpriced channel means nobody configured a rate for
     * it and the campaign would deliver for free.
     */
    private function warnAboutUnpricedServices(Campaign $campaign): void
    {
        $unpriced = $this->rates->unpriced($campaign->client, $campaign->channel->deliveryChannels());

        if ($unpriced === []) {
            return;
        }

        Log::warning('Campaign was submitted without a billing rate for '.implode(', ', array_map(
            fn (Channel $channel): string => $channel->label(),
            $unpriced,
        )).'.', [
            'campaign' => $campaign->reference,
            'client_id' => $campaign->client_id,
        ]);
    }

    /**
     * What the campaign still has to be billed, less what it has already spent.
     */
    private function outstandingEstimate(Campaign $campaign): float
    {
        return round(max((float) $campaign->estimated_cost - $this->wallet->chargedFor($campaign), 0.0), WalletService::PRECISION);
    }

    /**
     * Release every open reservation on the campaign.
     */
    protected function releaseReservation(Campaign $campaign, string $description): void
    {
        $campaign->reservations()
            ->where('status', ReservationStatus::Held->value)
            ->get()
            ->each(function (BillingReservation $reservation) use ($description): void {
                $this->wallet->release($reservation, $description);
            });

        $campaign->forceFill(['reserved_amount' => 0])->save();
    }

    /**
     * Whether this campaign may skip the review desk because every asset gate
     * it delivers under is already approved.
     */
    private function shouldAutoApprove(Campaign $campaign): bool
    {
        $campaign->loadMissing('client');

        if ($this->configuration->campaignApprovalRequired($campaign->client)) {
            return false;
        }

        return $this->readiness->isReady($campaign);
    }

    /**
     * Approve the campaign without a reviewer signing it off, and freeze the
     * approved version so a later edit is still detected before launch.
     */
    private function autoApprove(Campaign $campaign, ?User $user): Campaign
    {
        $campaign->forceFill([
            'status' => CampaignStatus::Approved,
            'approved_at' => now(),
            'approved_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ])->save();

        $this->versions->record($campaign, CampaignStatus::Approved, $user);

        return $campaign;
    }

    /**
     * Build a unique, human readable campaign reference.
     */
    protected function generateReference(): string
    {
        do {
            $reference = 'CMP-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (Campaign::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }

    private function ensureChannelsEnabled(Campaign $campaign): void
    {
        $campaign->loadMissing('client');

        foreach ($campaign->channel->deliveryChannels() as $channel) {
            if (! $this->configuration->channelEnabled($channel->value, $campaign->client)) {
                throw ValidationException::withMessages([
                    'channel' => $channel->label().' delivery is disabled by platform settings.',
                ]);
            }
        }
    }

    /**
     * Every submission sends the voice audio back to the review queue.
     *
     * @return array<string, mixed>
     */
    private function pendingAudioReview(): array
    {
        return [
            'audio_status' => CampaignAudioStatus::Pending,
            'audio_reviewed_at' => null,
            'audio_reviewed_by' => null,
            'audio_rejection_reason' => null,
        ];
    }

    /**
     * Only voice campaigns carry audio, and only while a decision is still open.
     */
    private function assertAudioReviewable(Campaign $campaign): void
    {
        if (! $campaign->requiresAudioApproval()) {
            throw ValidationException::withMessages([
                'campaign' => 'Only voice campaigns carry audio for review.',
            ]);
        }

        if (! $campaign->audio_status->canBeReviewed()) {
            throw ValidationException::withMessages([
                'campaign' => 'This campaign audio has already been approved.',
            ]);
        }
    }
}
