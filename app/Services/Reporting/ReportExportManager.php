<?php

namespace App\Services\Reporting;

use App\Enums\ReportExportStatus;
use App\Enums\ReportType;
use App\Jobs\GenerateReportExport;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Owns the lifecycle of a report file.
 *
 * A request creates the row and queues the job; a scheduled report creates the
 * same row for a known period, generates it immediately (there is nobody
 * waiting on a screen) and hands it to the mailer. Both paths end in the same
 * `completed` row, so the Exports screen lists everything either way.
 */
class ReportExportManager
{
    public function __construct(protected ReportExporter $exporter) {}

    /**
     * Queue an export the user asked for.
     *
     * @param  array<string, mixed>  $filters
     */
    public function create(User $user, ReportType $type, ReportPeriod $period, array $filters = []): ReportExport
    {
        abort_if($type->isPlatformOnly() && ! $user->isPlatformUser(), 403);

        $clientId = $user->isPlatformUser()
            ? (isset($filters['client_id']) && (int) $filters['client_id'] > 0 ? (int) $filters['client_id'] : null)
            : $user->client_id;

        $export = ReportExport::query()->create([
            'client_id' => $clientId,
            'scope' => $this->scopeKey($user->isPlatformUser(), $clientId),
            'requested_by' => $user->id,
            'report' => $type,
            'format' => 'csv',
            'status' => ReportExportStatus::Pending,
            'filters' => [
                ...$period->toArray(),
                'campaign' => isset($filters['campaign']) ? (int) $filters['campaign'] : null,
                'channel' => (string) ($filters['channel'] ?? ''),
            ],
            'source' => 'manual',
            'period_start' => $period->from->toDateString(),
            'period_end' => $period->to->toDateString(),
            'file_name' => $this->uniqueFileName($type, $period),
        ]);

        GenerateReportExport::dispatch($export->id);

        return $export;
    }

    /**
     * Generate (once) and return the export a scheduled report is built from.
     */
    public function schedule(ReportType $type, ReportScope $scope, ReportPeriod $period): ReportExport
    {
        $export = ReportExport::query()->firstOrCreate(
            [
                'scope' => $this->scopeKey($scope->platform, $scope->clientId),
                'report' => $type,
                'period_start' => $period->from->toDateString(),
                'period_end' => $period->to->toDateString(),
            ],
            [
                'client_id' => $scope->clientId,
                'requested_by' => null,
                'format' => 'csv',
                'status' => ReportExportStatus::Pending,
                'filters' => $period->toArray(),
                'source' => 'scheduled',
                'file_name' => $this->scheduledFileName($type, $period),
            ],
        );

        if (! $export->isReady()) {
            $this->generate($export);
        }

        return $export;
    }

    /**
     * Write the file and move the row to a terminal state.
     */
    public function generate(ReportExport $export): ReportExport
    {
        $export->forceFill([
            'status' => ReportExportStatus::Processing,
            'error_message' => null,
        ])->save();

        try {
            $result = $this->exporter->write(
                $export,
                ReportScope::fromExport($export->client_id, $export->isPlatformScope()),
                $this->period($export),
                $export->filters ?? [],
            );

            $export->forceFill([
                'status' => ReportExportStatus::Completed,
                'file_path' => $result['path'],
                'rows' => $result['rows'],
                'size_bytes' => $result['size'],
                'completed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $export->forceFill([
                'status' => ReportExportStatus::Failed,
                'error_message' => Str::limit($exception->getMessage(), 500),
            ])->save();

            throw $exception;
        }

        return $export;
    }

    /**
     * Serve the file a completed export wrote.
     */
    public function download(ReportExport $export): StreamedResponse
    {
        abort_unless($export->isReady(), 404);

        $disk = Storage::disk((string) config('reports.exports.disk', 'local'));

        abort_unless($disk->exists((string) $export->file_path), 404);

        return $disk->download(
            (string) $export->file_path,
            $export->file_name ?? basename((string) $export->file_path),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * Whether the user may open the export.
     */
    public function canAccess(User $user, ReportExport $export): bool
    {
        if ($user->isPlatformUser()) {
            return true;
        }

        return $export->client_id !== null && $export->client_id === $user->client_id;
    }

    /**
     * The unique key that scopes one export away from another.
     */
    public function scopeKey(bool $platform, ?int $clientId): string
    {
        return $platform
            ? 'platform:'.($clientId ?? 'all')
            : 'client:'.$clientId;
    }

    /**
     * The exports a user may see, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function listing(User $user, int $limit = 40): array
    {
        return ReportExport::query()
            ->when(
                ! $user->isPlatformUser(),
                fn ($query) => $query->where('client_id', $user->client_id),
            )
            ->with(['client:id,name', 'requester:id,name'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (ReportExport $export): array => [
                'id' => $export->id,
                'report' => $export->report->value,
                'report_label' => $export->report->label(),
                'status' => $export->status->value,
                'status_label' => $export->status->label(),
                'source' => $export->source,
                'period' => $export->periodLabel(),
                'rows' => $export->rows,
                'size' => $export->size_bytes,
                'file_name' => $export->file_name,
                'downloadable' => $export->isReady(),
                'expired' => $export->status === ReportExportStatus::Completed && $export->file_path === null,
                'error_message' => $export->error_message,
                'client' => $export->client?->name,
                'requester' => $export->requester?->name,
                'created_at' => $export->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * The report types a user may export.
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(User $user): array
    {
        $types = $user->isPlatformUser()
            ? [...ReportType::clientReports(), ...ReportType::platformReports()]
            : ReportType::clientReports();

        return array_map(
            fn (ReportType $type): array => ['value' => $type->value, 'label' => $type->label()],
            $types,
        );
    }

    /**
     * The window an export was requested for.
     */
    protected function period(ReportExport $export): ReportPeriod
    {
        if ($export->period_start !== null && $export->period_end !== null) {
            return ReportPeriod::between($export->period_start, $export->period_end);
        }

        $filters = $export->filters ?? [];

        return ReportPeriod::between(
            $filters['from'] ?? now()->toDateString(),
            $filters['to'] ?? now()->toDateString(),
        );
    }

    /**
     * A manual export needs a free name: two requests for the same window are
     * legitimate and must not overwrite each other.
     */
    protected function uniqueFileName(ReportType $type, ReportPeriod $period): string
    {
        return $type->fileStem()
            .'-'.$period->from->format('Ymd')
            .'-'.$period->to->format('Ymd')
            .'-'.Str::lower(Str::random(6)).'.csv';
    }

    /**
     * A scheduled export is named after the period so a rerun reuses it.
     */
    protected function scheduledFileName(ReportType $type, ReportPeriod $period): string
    {
        return $type->fileStem()
            .'-'.$period->from->format('Ymd')
            .'-'.$period->to->format('Ymd')
            .'.csv';
    }
}
