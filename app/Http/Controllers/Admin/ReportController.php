<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Channel;
use App\Enums\Permission;
use App\Enums\ReportType;
use App\Http\Controllers\Concerns\ResolvesTenantClient;
use App\Http\Controllers\Controller;
use App\Models\ReportExport;
use App\Services\Reporting\ChannelDeliveryReport;
use App\Services\Reporting\ClientPerformanceReport;
use App\Services\Reporting\ExecutiveReport;
use App\Services\Reporting\ProviderPerformanceReport;
use App\Services\Reporting\ReportExportManager;
use App\Services\Reporting\ReportPeriod;
use App\Services\Reporting\ReportScope;
use App\Services\Reporting\RevenueReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The platform team's analytics screens.
 *
 * Every screen reads across clients by default and narrows to one client when
 * the operator picks one, which is how an account manager reconciles a single
 * tenant without losing the platform-wide comparison.
 */
class ReportController extends Controller
{
    use ResolvesTenantClient;

    /**
     * Show the cross-client executive summary.
     */
    public function index(Request $request, ExecutiveReport $reports): Response
    {
        Gate::authorize(Permission::ViewReports->value);

        [$period, $scope] = $this->context($request);

        return Inertia::render('admin/reports/index', [
            'filters' => [
                ...$period->toArray(),
                'client_id' => $scope->clientId,
            ],
            'period' => [
                'label' => $period->label(),
                'days' => $period->days(),
            ],
            ...$reports->for($scope, $period),
            'clients' => $this->selectableClients($request),
            'can' => [
                'export' => Gate::allows(Permission::ExportReports->value),
            ],
        ]);
    }

    /**
     * Show one row per client with its delivery and money totals.
     */
    public function clients(Request $request, ClientPerformanceReport $reports): Response
    {
        Gate::authorize(Permission::ViewReports->value);

        [$period, $scope] = $this->context($request);

        return Inertia::render('admin/reports/clients', [
            'filters' => [
                ...$period->toArray(),
                'client_id' => $scope->clientId,
            ],
            'period' => [
                'label' => $period->label(),
                'days' => $period->days(),
            ],
            'report' => $reports->for($scope, $period),
            'clients' => $this->selectableClients($request),
            'can' => [
                'export' => Gate::allows(Permission::ExportReports->value),
            ],
        ]);
    }

    /**
     * Show the revenue and wallet-credit position.
     */
    public function revenue(Request $request, RevenueReport $reports): Response
    {
        Gate::authorize(Permission::ViewReports->value);

        [$period, $scope] = $this->context($request);

        return Inertia::render('admin/reports/revenue', [
            'filters' => [
                ...$period->toArray(),
                'client_id' => $scope->clientId,
            ],
            'period' => [
                'label' => $period->label(),
                'days' => $period->days(),
            ],
            ...$reports->for($scope, $period),
            'clients' => $this->selectableClients($request),
            'can' => [
                'export' => Gate::allows(Permission::ExportReports->value),
            ],
        ]);
    }

    /**
     * Show how each transport performed and whether the ledgers agree.
     */
    public function providers(Request $request, ProviderPerformanceReport $reports): Response
    {
        Gate::authorize(Permission::ViewReports->value);

        [$period, $scope] = $this->context($request);

        return Inertia::render('admin/reports/providers', [
            'filters' => [
                ...$period->toArray(),
                'client_id' => $scope->clientId,
            ],
            'period' => [
                'label' => $period->label(),
                'days' => $period->days(),
            ],
            ...$reports->for($scope, $period),
            'clients' => $this->selectableClients($request),
            'can' => [
                'export' => Gate::allows(Permission::ExportReports->value),
            ],
        ]);
    }

    /**
     * Show how the voice channel performed across clients.
     */
    public function voice(Request $request, ChannelDeliveryReport $reports): Response
    {
        return $this->channel($request, $reports, Channel::Voice);
    }

    /**
     * Show how the SMS channel performed across clients.
     */
    public function sms(Request $request, ChannelDeliveryReport $reports): Response
    {
        return $this->channel($request, $reports, Channel::Sms);
    }

    /**
     * Show how the email channel performed across clients.
     */
    public function email(Request $request, ChannelDeliveryReport $reports): Response
    {
        return $this->channel($request, $reports, Channel::Email);
    }

    /**
     * The report window and tenant scope a screen was opened with.
     *
     * @return array{0: ReportPeriod, 1: ReportScope}
     */
    protected function context(Request $request): array
    {
        $clientId = $request->integer('client_id');

        return [
            ReportPeriod::fromRequest($request, (int) config('reports.default_window_days', 30)),
            ReportScope::forUser($request->user(), $clientId > 0 ? $clientId : null),
        ];
    }

    /**
     * Render one channel screen for the resolved window and scope.
     */
    protected function channel(Request $request, ChannelDeliveryReport $reports, Channel $channel): Response
    {
        Gate::authorize(Permission::ViewReports->value);

        [$period, $scope] = $this->context($request);

        return Inertia::render('admin/reports/channel', [
            'filters' => [
                ...$period->toArray(),
                'client_id' => $scope->clientId,
            ],
            'period' => [
                'label' => $period->label(),
                'days' => $period->days(),
            ],
            ...$reports->for($channel, $scope, $period),
            'clients' => $this->selectableClients($request),
            'can' => [
                'export' => Gate::allows(Permission::ExportReports->value),
            ],
        ]);
    }

    /**
     * The exports the platform generated, including the scheduled ones.
     */
    public function exportHistory(Request $request, ReportExportManager $manager): Response
    {
        Gate::authorize(Permission::ViewReports->value);

        [$period] = $this->context($request);

        return Inertia::render('admin/reports/exports', [
            'exports' => $manager->listing($request->user()),
            'reports' => $manager->options($request->user()),
            'report' => $request->string('report')->toString(),
            'filters' => $period->toArray(),
            'clients' => $this->selectableClients($request),
            'can' => [
                'export' => Gate::allows(Permission::ExportReports->value),
            ],
        ]);
    }

    /**
     * Queue a platform export.
     */
    public function storeExport(Request $request, ReportExportManager $manager): RedirectResponse
    {
        Gate::authorize(Permission::ExportReports->value);

        $data = $request->validate([
            'report' => ['required', Rule::enum(ReportType::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'campaign' => ['nullable', 'integer'],
            'channel' => ['nullable', 'string', 'max:32'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ]);

        $period = ReportPeriod::between(
            $data['from'] ?? now()->subDays(29)->toDateString(),
            $data['to'] ?? now()->toDateString(),
        );

        $manager->create($request->user(), ReportType::from($data['report']), $period, $data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'The export is being generated.',
        ]);

        return back();
    }

    /**
     * Download a generated report file.
     */
    public function downloadExport(Request $request, ReportExport $export, ReportExportManager $manager): StreamedResponse
    {
        Gate::authorize(Permission::ViewReports->value);

        return $manager->download($export);
    }
}
