<?php

namespace App\Http\Controllers;

use App\Enums\CampaignContactStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\Permission;
use App\Http\Controllers\Concerns\ResolvesTenantClient;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\EmailMessage;
use App\Models\SmsMessage;
use App\Models\User;
use App\Models\VoiceCall;
use App\Services\EmailMessageReport;
use App\Services\SmsMessageReport;
use App\Services\VoiceCallReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    use ResolvesTenantClient;

    /**
     * Show delivery and spend reporting for a date range.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(Permission::ViewReports->value), 403);

        /** @var User $user */
        $user = $request->user();

        $to = Carbon::parse($request->string('to')->toString() ?: null)->endOfDay();
        $from = Carbon::parse($request->string('from')->toString() ?: null)->startOfDay();
        $channel = $request->string('channel')->toString();

        $campaigns = Campaign::query()
            ->forUser($user)
            ->whereBetween('created_at', [$from, $to])
            ->when($channel !== '', fn ($query) => $query->where('channel', $channel));

        $recipients = CampaignContact::query()
            ->whereHas('campaign', fn ($query) => $query
                ->forUser($user)
                ->whereBetween('created_at', [$from, $to])
                ->when($channel !== '', fn ($query) => $query->where('channel', $channel)));

        $statusTotals = (clone $recipients)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $delivered = (int) $statusTotals->get(CampaignContactStatus::Delivered->value, 0)
            + (int) $statusTotals->get(CampaignContactStatus::Sent->value, 0);
        $failed = (int) $statusTotals->get(CampaignContactStatus::Failed->value, 0);
        $skipped = (int) $statusTotals->get(CampaignContactStatus::Skipped->value, 0);
        $attempted = $delivered + $failed + $skipped;

        return Inertia::render('reports/index', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'channel' => $channel,
            ],
            'summary' => [
                'campaigns' => (clone $campaigns)->count(),
                'completed_campaigns' => (clone $campaigns)->where('status', CampaignStatus::Completed->value)->count(),
                'recipients' => (int) $statusTotals->sum(),
                'delivered' => $delivered,
                'failed' => $failed,
                'skipped' => $skipped,
                'spend' => round((float) (clone $campaigns)->sum('actual_cost'), 2),
                'reserved' => round((float) (clone $campaigns)->sum('reserved_amount'), 2),
                'delivery_rate' => $attempted > 0 ? round($delivered / $attempted * 100, 1) : null,
            ],
            'byChannel' => collect(Channel::cases())
                ->map(function (Channel $channelCase) use ($user, $from, $to): array {
                    $query = Campaign::query()
                        ->forUser($user)
                        ->where('channel', $channelCase->value)
                        ->whereBetween('created_at', [$from, $to]);

                    return [
                        'channel' => $channelCase->value,
                        'label' => $channelCase->label(),
                        'campaigns' => (clone $query)->count(),
                        'recipients' => (int) (clone $query)->sum('estimated_recipients'),
                        'spend' => round((float) (clone $query)->sum('actual_cost'), 2),
                    ];
                })
                ->all(),
            'monthly' => collect(range(5, 0))
                ->map(function (int $monthsAgo) use ($user): array {
                    $month = now()->subMonths($monthsAgo);
                    $query = Campaign::query()
                        ->forUser($user)
                        ->whereYear('created_at', $month->year)
                        ->whereMonth('created_at', $month->month);

                    return [
                        'month' => $month->format('Y-m'),
                        'label' => $month->format('M Y'),
                        'campaigns' => (clone $query)->count(),
                        'spend' => round((float) (clone $query)->sum('actual_cost'), 2),
                    ];
                })
                ->all(),
            'topCampaigns' => (clone $campaigns)
                ->orderByDesc('actual_cost')
                ->limit(5)
                ->get(['id', 'reference', 'name', 'channel', 'actual_cost', 'estimated_recipients'])
                ->map(fn (Campaign $campaign): array => [
                    'id' => $campaign->id,
                    'reference' => $campaign->reference,
                    'name' => $campaign->name,
                    'channel_label' => $campaign->channel->label(),
                    'recipients' => $campaign->estimated_recipients,
                    'spend' => (float) $campaign->actual_cost,
                ])
                ->all(),
            'channels' => array_map(
                fn (Channel $channelCase): array => ['value' => $channelCase->value, 'label' => $channelCase->label()],
                Channel::cases(),
            ),
            'can' => [
                'export' => Gate::allows(Permission::ExportReports->value),
            ],
            'clients' => $this->selectableClients($request),
        ]);
    }

    /**
     * Show the voice call report for a date range.
     *
     * Answer rate, talk time and the DTMF breakdown come from the call log the
     * switch writes through the signed webhook, so the report reflects what the
     * PBX actually did rather than what the dispatcher handed it.
     */
    public function voice(Request $request, VoiceCallReport $reports): Response
    {
        abort_unless($request->user()->can(Permission::ViewReports->value), 403);

        /** @var User $user */
        $user = $request->user();

        $to = Carbon::parse($request->string('to')->toString() ?: null)->endOfDay();
        $from = Carbon::parse($request->string('from')->toString() ?: null)->startOfDay();
        $campaignId = $request->integer('campaign');

        $calls = VoiceCall::query()
            ->forUser($user)
            ->whereBetween('dialled_at', [$from, $to])
            ->when($campaignId > 0, fn (Builder $query) => $query->where('campaign_id', $campaignId));

        return Inertia::render('reports/voice', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'campaign' => $campaignId > 0 ? $campaignId : null,
            ],
            'report' => $reports->forQuery($calls, 25),
            'campaigns' => Campaign::query()
                ->forUser($user)
                ->whereIn('channel', [Channel::Voice->value, Channel::MultiChannel->value])
                ->latest('id')
                ->limit(100)
                ->get(['id', 'reference', 'name'])
                ->map(fn (Campaign $campaign): array => [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'reference' => $campaign->reference,
                ])
                ->all(),
            'clients' => $this->selectableClients($request),
        ]);
    }

    /**
     * Show the SMS delivery report for a date range.
     *
     * Delivery rate and the reason a message failed come from the receipts the
     * network posts through the signed webhook, so the report reflects what the
     * carrier confirmed rather than what the dispatcher handed it.
     */
    public function sms(Request $request, SmsMessageReport $reports): Response
    {
        abort_unless($request->user()->can(Permission::ViewReports->value), 403);

        /** @var User $user */
        $user = $request->user();

        $to = Carbon::parse($request->string('to')->toString() ?: null)->endOfDay();
        $from = Carbon::parse($request->string('from')->toString() ?: null)->startOfDay();
        $campaignId = $request->integer('campaign');

        $messages = SmsMessage::query()
            ->forUser($user)
            ->whereBetween('created_at', [$from, $to])
            ->when($campaignId > 0, fn (Builder $query) => $query->where('campaign_id', $campaignId));

        return Inertia::render('reports/sms', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'campaign' => $campaignId > 0 ? $campaignId : null,
            ],
            'report' => $reports->forQuery($messages, 25),
            'campaigns' => Campaign::query()
                ->forUser($user)
                ->whereIn('channel', [Channel::Sms->value, Channel::MultiChannel->value])
                ->latest('id')
                ->limit(100)
                ->get(['id', 'reference', 'name'])
                ->map(fn (Campaign $campaign): array => [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'reference' => $campaign->reference,
                ])
                ->all(),
            'clients' => $this->selectableClients($request),
        ]);
    }

    /**
     * Show the email delivery report for a date range.
     *
     * SMTP reports nothing on its own, so the delivery rate, bounces and
     * complaints come from the feedback the mail server posts through the signed
     * webhook.
     */
    public function email(Request $request, EmailMessageReport $reports): Response
    {
        abort_unless($request->user()->can(Permission::ViewReports->value), 403);

        /** @var User $user */
        $user = $request->user();

        $to = Carbon::parse($request->string('to')->toString() ?: null)->endOfDay();
        $from = Carbon::parse($request->string('from')->toString() ?: null)->startOfDay();
        $campaignId = $request->integer('campaign');

        $messages = EmailMessage::query()
            ->forUser($user)
            ->whereBetween('created_at', [$from, $to])
            ->when($campaignId > 0, fn (Builder $query) => $query->where('campaign_id', $campaignId));

        return Inertia::render('reports/email', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'campaign' => $campaignId > 0 ? $campaignId : null,
            ],
            'report' => $reports->forQuery($messages, 25),
            'campaigns' => Campaign::query()
                ->forUser($user)
                ->where('channel', Channel::Email->value)
                ->latest('id')
                ->limit(100)
                ->get(['id', 'reference', 'name'])
                ->map(fn (Campaign $campaign): array => [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'reference' => $campaign->reference,
                ])
                ->all(),
            'clients' => $this->selectableClients($request),
        ]);
    }
}
