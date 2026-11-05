<?php

namespace App\Http\Controllers;

use App\Enums\Channel;
use App\Enums\Permission;
use App\Enums\ReportType;
use App\Http\Controllers\Concerns\ResolvesTenantClient;
use App\Models\Campaign;
use App\Models\EmailMessage;
use App\Models\ReportExport;
use App\Models\SmsMessage;
use App\Models\User;
use App\Models\VoiceCall;
use App\Services\EmailMessageReport;
use App\Services\Reporting\CampaignPerformanceReport;
use App\Services\Reporting\ContactOutcomeReport;
use App\Services\Reporting\OverviewReport;
use App\Services\Reporting\ReportExportManager;
use App\Services\Reporting\ReportPeriod;
use App\Services\Reporting\ReportScope;
use App\Services\SmsMessageReport;
use App\Services\VoiceCallReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ResolvesTenantClient;

    /**
     * Show delivery and spend reporting for a date range.
     */
    public function index(Request $request, OverviewReport $overview): Response
    {
        abort_unless($request->user()->can(Permission::ViewReports->value), 403);

        /** @var User $user */
        $user = $request->user();

        $period = ReportPeriod::fromRequest($request, (int) config('reports.default_window_days', 30));
        $channel = $request->string('channel')->toString();

        return Inertia::render('reports/index', [
            'filters' => [
                ...$period->toArray(),
                'channel' => $channel,
            ],
            'period' => [
                'label' => $period->label(),
                'days' => $period->days(),
            ],
            ...$overview->for(ReportScope::forUser($user), $period, $channel),
            'channels' => $this->channelOptions(),
            'can' => [
                'export' => Gate::allows(Permission::ExportReports->value),
            ],
        ]);
    }

    /**
     * Show one row per campaign with the outcomes its audience produced.
     */
    public function campaigns(Request $request, CampaignPerformanceReport $reports): Response
    {
        abort_unless($request->user()->can(Permission::ViewReports->value), 403);

        /** @var User $user */
        $user = $request->user();

        $period = ReportPeriod::fromRequest($request, (int) config('reports.default_window_days', 30));
        $filters = [
            'campaign' => $request->integer('campaign'),
            'channel' => $request->string('channel')->toString(),
        ];

        return Inertia::render('reports/campaigns', [
            'filters' => [
                ...$period->toArray(),
                ...$filters,
            ],
            'period' => [
                'label' => $period->label(),
                'days' => $period->days(),
            ],
            'report' => $reports->for(ReportScope::forUser($user), $period, $filters),
            'channels' => $this->channelOptions(),
            'campaigns' => $this->campaignOptions($user),
            'can' => [
                'export' => Gate::allows(Permission::ExportReports->value),
            ],
        ]);
    }

    /**
     * Show how the audience answered the campaigns that reached it.
     */
    public function contacts(Request $request, ContactOutcomeReport $reports): Response
    {
        abort_unless($request->user()->can(Permission::ViewReports->value), 403);

        /** @var User $user */
        $user = $request->user();

        $period = ReportPeriod::fromRequest($request, (int) config('reports.default_window_days', 30));

        return Inertia::render('reports/contacts', [
            'filters' => $period->toArray(),
            'period' => [
                'label' => $period->label(),
                'days' => $period->days(),
            ],
            'report' => $reports->for(ReportScope::forUser($user), $period),
            'can' => [
                'export' => Gate::allows(Permission::ExportReports->value),
            ],
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

    /**
     * Show the exports the requester (or the platform) generated.
     */
    public function exportHistory(Request $request, ReportExportManager $manager): Response
    {
        abort_unless($request->user()->can(Permission::ViewReports->value), 403);

        $period = ReportPeriod::fromRequest($request, (int) config('reports.default_window_days', 30));

        return Inertia::render('reports/exports', [
            'exports' => $manager->listing($request->user()),
            'reports' => $manager->options($request->user()),
            'report' => $request->string('report')->toString(),
            'filters' => $period->toArray(),
            'can' => [
                'export' => Gate::allows(Permission::ExportReports->value),
            ],
        ]);
    }

    /**
     * Queue an export of the current report window.
     */
    public function storeExport(Request $request, ReportExportManager $manager): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::ExportReports->value), 403);

        $data = $request->validate([
            'report' => ['required', Rule::enum(ReportType::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'campaign' => ['nullable', 'integer'],
            'channel' => ['nullable', 'string', 'max:32'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ]);

        $type = ReportType::from($data['report']);
        abort_if($type->isPlatformOnly() && ! $request->user()->isPlatformUser(), 403);

        $period = ReportPeriod::between(
            $data['from'] ?? now()->subDays(29)->toDateString(),
            $data['to'] ?? now()->toDateString(),
        );

        $manager->create($request->user(), $type, $period, $data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'The export is being generated. It will appear here when it is ready.',
        ]);

        return back();
    }

    /**
     * Download a generated report file.
     */
    public function downloadExport(Request $request, ReportExport $export, ReportExportManager $manager): StreamedResponse
    {
        abort_unless($request->user()->can(Permission::ViewReports->value), 403);
        abort_unless($manager->canAccess($request->user(), $export), 404);

        return $manager->download($export);
    }

    /**
     * The channel options a filter offers.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function channelOptions(): array
    {
        return array_map(
            fn (Channel $channel): array => ['value' => $channel->value, 'label' => $channel->label()],
            Channel::cases(),
        );
    }

    /**
     * The campaigns the requester may narrow a report to.
     *
     * @return list<array{id: int, name: string, reference: string}>
     */
    protected function campaignOptions(User $user): array
    {
        return Campaign::query()
            ->forUser($user)
            ->latest('id')
            ->limit(100)
            ->get(['id', 'reference', 'name'])
            ->map(fn (Campaign $campaign): array => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'reference' => $campaign->reference,
            ])
            ->values()
            ->all();
    }
}
