<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\ReservationStatus;
use App\Jobs\DispatchCampaignJob;
use App\Models\BillingReservation;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
    ) {}

    /**
     * Create a draft campaign and price it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $user): Campaign
    {
        $campaign = Campaign::query()->create([
            ...$attributes,
            'reference' => $attributes['reference'] ?? $this->generateReference(),
            'status' => CampaignStatus::Draft,
            'created_by' => $user->id,
        ]);

        return $this->refreshEstimate($campaign);
    }

    /**
     * Update an editable campaign and reprice it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Campaign $campaign, array $attributes): Campaign
    {
        $campaign->fill($attributes)->save();

        return $this->refreshEstimate($campaign);
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
     * Snapshot the audience, hold the funds and send the campaign for approval.
     */
    public function submit(Campaign $campaign, User $user): Campaign
    {
        return DB::transaction(function () use ($campaign, $user): Campaign {
            $campaign = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $this->ensureChannelsEnabled($campaign);
            $this->audience->build($campaign);
            $this->refreshEstimate($campaign);
            $this->quota->assertAvailable($campaign);

            $this->wallet->reserve($campaign, (float) $campaign->estimated_cost, $user);

            $campaign->forceFill([
                'status' => CampaignStatus::PendingApproval,
                'submitted_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
            ])->save();

            return $campaign;
        });

        return $campaign;
    }

    /**
     * Approve a campaign that is waiting for review.
     */
    public function approve(Campaign $campaign, User $user): Campaign
    {
        $campaign->forceFill([
            'status' => CampaignStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $user->id,
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

        return $campaign;
    }

    /**
     * Launch an approved campaign now or schedule it for later.
     */
    public function launch(Campaign $campaign): Campaign
    {
        $this->ensureChannelsEnabled($campaign);
        $scheduled = $campaign->schedule_type === 'scheduled'
            && $campaign->scheduled_at !== null
            && $campaign->scheduled_at->isFuture();

        $campaign->forceFill([
            'status' => $scheduled ? CampaignStatus::Scheduled : CampaignStatus::Running,
            'launched_at' => $scheduled ? null : now(),
        ])->save();

        $job = DispatchCampaignJob::dispatch($campaign->id);

        if ($scheduled) {
            $job->delay($campaign->scheduled_at);
        }

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

        return $campaign;
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
}
