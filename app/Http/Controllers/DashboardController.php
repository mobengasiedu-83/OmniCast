<?php

namespace App\Http\Controllers;

use App\Enums\CampaignContactStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\ClientStatus;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use App\Services\ModuleAvailability;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected WalletService $wallets,
        protected ModuleAvailability $modules,
    ) {}

    /**
     * Show the delivery overview for the signed-in user.
     */
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', Campaign::class);

        /** @var User $user */
        $user = $request->user();

        if ($user->isPlatformUser()) {
            $now = now();
            $today = $now->copy()->startOfDay();
            $activityStart = $today->copy()->subDays(6);

            $campaignStatusTotals = Campaign::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $channelTotals = Campaign::query()
                ->selectRaw('channel, count(*) as total')
                ->groupBy('channel')
                ->pluck('total', 'channel');

            $recipientStatusTotals = CampaignContact::query()
                ->whereIn('campaign_id', Campaign::query()->select('id'))
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $campaignActivityTotals = Campaign::query()
                ->whereBetween('created_at', [$activityStart, $now])
                ->selectRaw('date(created_at) as date, count(*) as total')
                ->groupByRaw('date(created_at)')
                ->orderBy('date')
                ->get()
                ->keyBy('date');

            return Inertia::render('admin/dashboard', [
                'stats' => [
                    'clients' => Client::query()->count(),
                    'pending_clients' => Client::query()
                        ->where('status', ClientStatus::Pending->value)
                        ->count(),
                    'campaigns' => Campaign::query()->count(),
                    'pending_campaigns' => Campaign::query()
                        ->where('status', CampaignStatus::PendingApproval->value)
                        ->count(),
                    'active_campaigns' => Campaign::query()
                        ->whereIn('status', [
                            CampaignStatus::Scheduled->value,
                            CampaignStatus::Running->value,
                            CampaignStatus::Paused->value,
                        ])
                        ->count(),
                ],
                'campaignActivity' => collect(range(6, 0))
                    ->map(function (int $daysAgo) use ($today, $campaignActivityTotals): array {
                        $date = $today->copy()->subDays($daysAgo);
                        $key = $date->toDateString();

                        return [
                            'date' => $key,
                            'label' => $date->format('D'),
                            'total' => (int) ($campaignActivityTotals->get($key)?->total ?? 0),
                        ];
                    })
                    ->all(),
                'campaignStatusBreakdown' => collect(CampaignStatus::cases())
                    ->map(fn (CampaignStatus $status): array => [
                        'status' => $status->value,
                        'label' => $status->label(),
                        'total' => (int) $campaignStatusTotals->get($status->value, 0),
                    ])
                    ->filter(fn (array $status): bool => $status['total'] > 0)
                    ->values()
                    ->all(),
                'channelBreakdown' => collect(Channel::cases())
                    ->map(fn (Channel $channel): array => [
                        'channel' => $channel->value,
                        'label' => $channel->label(),
                        'total' => (int) $channelTotals->get($channel->value, 0),
                    ])
                    ->all(),
                'recipientStatusBreakdown' => collect(CampaignContactStatus::cases())
                    ->map(fn (CampaignContactStatus $status): array => [
                        'status' => $status->value,
                        'label' => $status->label(),
                        'total' => (int) $recipientStatusTotals->get($status->value, 0),
                    ])
                    ->filter(fn (array $status): bool => $status['total'] > 0)
                    ->values()
                    ->all(),
                'recentCampaigns' => Campaign::query()
                    ->with('client:id,name')
                    ->latest()
                    ->limit(6)
                    ->get([
                        'id',
                        'client_id',
                        'reference',
                        'name',
                        'channel',
                        'status',
                        'estimated_recipients',
                        'created_at',
                    ])
                    ->map(fn (Campaign $campaign): array => [
                        'id' => $campaign->id,
                        'reference' => $campaign->reference,
                        'name' => $campaign->name,
                        'client_name' => $campaign->client?->name ?? 'Unknown client',
                        'channel' => $campaign->channel->value,
                        'channel_label' => $campaign->channel->label(),
                        'status' => $campaign->status->value,
                        'status_label' => $campaign->status->label(),
                        'estimated_recipients' => $campaign->estimated_recipients,
                        'created_at' => $campaign->created_at?->toIso8601String(),
                    ])
                    ->all(),
            ]);
        }

        $campaigns = Campaign::query()->forUser($user);

        $wallet = $user->client !== null ? $this->wallets->walletFor($user->client) : null;

        return Inertia::render('dashboard', [
            'stats' => [
                'campaigns' => (clone $campaigns)->count(),
                'active_campaigns' => (clone $campaigns)->whereIn('status', [
                    CampaignStatus::Scheduled->value,
                    CampaignStatus::Running->value,
                    CampaignStatus::Paused->value,
                ])->count(),
                'awaiting_approval' => (clone $campaigns)->where('status', CampaignStatus::PendingApproval->value)->count(),
                'contacts' => Contact::query()->forUser($user)->count(),
                'delivered' => CampaignContact::query()
                    ->whereIn('status', [CampaignContactStatus::Sent->value, CampaignContactStatus::Delivered->value])
                    ->whereHas('campaign', fn ($query) => $query->forUser($user))
                    ->count(),
                'spend_this_month' => round((float) (clone $campaigns)
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->sum('actual_cost'), 2),
            ],
            'wallet' => $wallet === null ? null : [
                'id' => $wallet->id,
                'currency' => $wallet->currency,
                'balance' => (float) $wallet->balance,
                'reserved' => (float) $wallet->reserved_balance,
                'available' => $wallet->availableBalance(),
                'low_balance_threshold' => (float) $wallet->low_balance_threshold,
                'is_low' => $wallet->isLow(),
            ],
            'channelBreakdown' => collect(Channel::cases())
                ->filter(fn (Channel $channel): bool => $this->modules->allowsChannel($channel, $user->client))
                ->map(fn (Channel $channel): array => [
                    'channel' => $channel->value,
                    'label' => $channel->label(),
                    'total' => (clone $campaigns)->where('channel', $channel->value)->count(),
                ])
                ->all(),
            'recentCampaigns' => (clone $campaigns)
                ->with(['senderId:id,name', 'contactList:id,name'])
                ->latest()
                ->limit(6)
                ->get()
                ->map(fn (Campaign $campaign): array => [
                    'id' => $campaign->id,
                    'reference' => $campaign->reference,
                    'name' => $campaign->name,
                    'channel' => $campaign->channel->value,
                    'channel_label' => $campaign->channel->label(),
                    'status' => $campaign->status->value,
                    'status_label' => $campaign->status->label(),
                    'audience' => $campaign->contactList?->name ?? 'All broadcastable contacts',
                    'sender' => $campaign->senderId?->name,
                    'estimated_recipients' => $campaign->estimated_recipients,
                    'estimated_cost' => (float) $campaign->estimated_cost,
                    'actual_cost' => (float) $campaign->actual_cost,
                    'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
                    'created_at' => $campaign->created_at?->toIso8601String(),
                ])
                ->all(),
        ]);
    }
}
