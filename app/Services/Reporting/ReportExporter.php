<?php

namespace App\Services\Reporting;

use App\Enums\ReportType;
use App\Models\ReportExport;
use BackedEnum;
use Generator;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/**
 * Turns a report into a CSV file.
 *
 * The same service backs the async export and the scheduled report, so a file
 * a client downloads and the file mailed to them on a Monday are produced by
 * one code path. Rows are streamed from the database into a temporary stream,
 * which keeps a large export off the PHP heap.
 */
class ReportExporter
{
    public function __construct(
        protected OverviewReport $overview,
        protected CampaignPerformanceReport $campaigns,
        protected ContactOutcomeReport $contacts,
        protected ClientPerformanceReport $clients,
        protected RevenueReport $revenue,
        protected ProviderPerformanceReport $providers,
        protected ExecutiveReport $executive,
    ) {}

    /**
     * The heading and column order of a report.
     *
     * @return array{title: string, columns: list<string>}
     */
    public function schema(ReportType $type): array
    {
        return match ($type) {
            ReportType::Overview => [
                'title' => 'Overview',
                'columns' => ['Metric', 'Value'],
            ],
            ReportType::Campaigns => [
                'title' => 'Campaign performance',
                'columns' => [
                    'Reference', 'Campaign', 'Channel', 'Status', 'Recipients',
                    'Delivered', 'Failed', 'Skipped', 'Pending', 'Delivery rate',
                    'Spend', 'Created', 'Launched', 'Completed',
                ],
            ],
            ReportType::Contacts => [
                'title' => 'Recipient outcomes',
                'columns' => [
                    'Campaign', 'Channel', 'Recipient', 'Phone', 'Email', 'Status',
                    'Attempts', 'Cost', 'Submitted', 'Delivered', 'Error',
                ],
            ],
            ReportType::Voice => [
                'title' => 'Voice calls',
                'columns' => [
                    'Reference', 'Recipient', 'From', 'Status', 'DTMF', 'Attempts',
                    'Dialled', 'Duration (s)', 'Cost', 'Error',
                ],
            ],
            ReportType::Sms => [
                'title' => 'SMS messages',
                'columns' => [
                    'Reference', 'Recipient', 'From', 'Status', 'Segments',
                    'Attempts', 'Submitted', 'Delivered', 'Cost', 'Error',
                ],
            ],
            ReportType::Email => [
                'title' => 'Email messages',
                'columns' => [
                    'Reference', 'Recipient', 'From', 'Subject', 'Status',
                    'Attempts', 'Submitted', 'Delivered', 'Cost', 'Error',
                ],
            ],
            ReportType::Executive => [
                'title' => 'Executive summary',
                'columns' => ['Metric', 'Value'],
            ],
            ReportType::Clients => [
                'title' => 'Client performance',
                'columns' => [
                    'Client', 'Status', 'Campaigns', 'Recipients', 'Delivered',
                    'Failed', 'Skipped', 'Delivery rate', 'Spend', 'Revenue',
                    'Wallet balance', 'Reserved',
                ],
            ],
            ReportType::Revenue => [
                'title' => 'Revenue',
                'columns' => [
                    'Reference', 'Client', 'Provider', 'Status', 'Currency',
                    'Amount', 'Amount paid', 'Paid at', 'Created',
                ],
            ],
            ReportType::Providers => [
                'title' => 'Provider performance',
                'columns' => [
                    'Channel', 'Provider', 'Sent', 'Delivered', 'Failed',
                    'Pending', 'Delivery rate', 'Spend',
                ],
            ],
        };
    }

    /**
     * Write the report to storage and describe the file that was produced.
     *
     * @param  array<string, mixed>  $filters
     * @return array{path: string, rows: int, size: int}
     */
    public function write(
        ReportExport $export,
        ReportScope $scope,
        ReportPeriod $period,
        array $filters = [],
    ): array {
        $schema = $this->schema($export->report);
        $disk = Storage::disk((string) config('reports.exports.disk', 'local'));
        $relative = trim((string) config('reports.exports.path', 'reports'), '/')
            .'/'.($export->file_name ?? $this->fileName($export));

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('The report stream could not be opened.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $schema['columns']);

        $rows = 0;

        foreach ($this->rows($export->report, $scope, $period, $filters) as $row) {
            fputcsv($handle, $row);
            $rows++;
        }

        rewind($handle);

        $disk->put($relative, $handle);
        fclose($handle);

        return [
            'path' => $relative,
            'rows' => $rows,
            'size' => (int) $disk->size($relative),
        ];
    }

    /**
     * The name the file is saved and downloaded under.
     */
    public function fileName(ReportExport $export): string
    {
        $period = $export->period_start !== null && $export->period_end !== null
            ? $export->period_start->format('Ymd').'-'.$export->period_end->format('Ymd')
            : now()->format('Ymd-His');

        return $export->report->fileStem().'-'.$period.'.csv';
    }

    /**
     * Stream the report's data rows.
     *
     * @param  array<string, mixed>  $filters
     * @return Generator<int, list<string|int|float|null>>
     */
    public function rows(ReportType $type, ReportScope $scope, ReportPeriod $period, array $filters = []): Generator
    {
        yield from match ($type) {
            ReportType::Overview => $this->overviewRows($scope, $period, $filters),
            ReportType::Campaigns => $this->campaignRows($scope, $period, $filters),
            ReportType::Contacts => $this->contactRows($scope, $period, $filters),
            ReportType::Voice => $this->voiceRows($scope, $period, $filters),
            ReportType::Sms => $this->smsRows($scope, $period, $filters),
            ReportType::Email => $this->emailRows($scope, $period, $filters),
            ReportType::Executive => $this->executiveRows($scope, $period),
            ReportType::Clients => $this->clientRows($scope, $period),
            ReportType::Revenue => $this->revenueRows($scope, $period),
            ReportType::Providers => $this->providerRows($scope, $period),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Generator<int, list<string|int|float|null>>
     */
    protected function overviewRows(ReportScope $scope, ReportPeriod $period, array $filters): Generator
    {
        $report = $this->overview->for($scope, $period, (string) ($filters['channel'] ?? ''));

        yield from $this->metricRows($report['summary']);
    }

    /**
     * @return Generator<int, list<string|int|float|null>>
     */
    protected function executiveRows(ReportScope $scope, ReportPeriod $period): Generator
    {
        $report = $this->executive->for($scope, $period);

        yield from $this->metricRows($report['summary']);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return Generator<int, list<string|int|float|null>>
     */
    protected function metricRows(array $summary): Generator
    {
        foreach ($summary as $metric => $value) {
            yield [$this->heading((string) $metric), is_float($value) ? round($value, 2) : $value];
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Generator<int, list<string|int|float|null>>
     */
    protected function campaignRows(ReportScope $scope, ReportPeriod $period, array $filters): Generator
    {
        $report = $this->campaigns->for($scope, $period, $filters, (int) config('reports.exports.max_rows', 50000));

        foreach ($report['rows'] as $row) {
            yield [
                $row['reference'],
                $row['name'],
                $row['channel_label'],
                $row['status_label'],
                $row['recipients'],
                $row['delivered'],
                $row['failed'],
                $row['skipped'],
                $row['pending'],
                $row['delivery_rate'],
                $row['spend'],
                $row['created_at'],
                $row['launched_at'],
                $row['completed_at'],
            ];
        }
    }

    /**
     * @return Generator<int, list<string|int|float|null>>
     */
    protected function clientRows(ReportScope $scope, ReportPeriod $period): Generator
    {
        $report = $this->clients->for($scope, $period);

        foreach ($report['rows'] as $row) {
            yield [
                $row['name'],
                $row['status_label'],
                $row['campaigns'],
                $row['recipients'],
                $row['delivered'],
                $row['failed'],
                $row['skipped'],
                $row['delivery_rate'],
                $row['spend'],
                $row['revenue'],
                $row['balance'],
                $row['reserved'],
            ];
        }
    }

    /**
     * @return Generator<int, list<string|int|float|null>>
     */
    protected function providerRows(ReportScope $scope, ReportPeriod $period): Generator
    {
        $report = $this->providers->for($scope, $period);

        foreach (['sms', 'voice', 'email'] as $channel) {
            foreach ($report[$channel]['rows'] as $row) {
                yield [
                    $report[$channel]['label'],
                    $row['provider'],
                    $row['total'],
                    $row['delivered'],
                    $row['failed'],
                    $row['pending'],
                    $row['delivery_rate'],
                    $row['spend'],
                ];
            }
        }
    }

    /**
     * Recipient outcomes across every channel, one row per recipient.
     *
     * @param  array<string, mixed>  $filters
     * @return Generator<int, list<string|int|float|null>>
     */
    protected function contactRows(ReportScope $scope, ReportPeriod $period, array $filters): Generator
    {
        $campaignId = (int) ($filters['campaign'] ?? 0);
        $channel = (string) ($filters['channel'] ?? '');

        $rows = $scope->campaignContacts()
            ->join('campaigns', 'campaigns.id', '=', 'campaign_contacts.campaign_id')
            ->leftJoin('contacts', 'contacts.id', '=', 'campaign_contacts.contact_id')
            ->whereBetween('campaign_contacts.created_at', [$period->from, $period->to])
            ->when($campaignId > 0, fn (Builder $query): Builder => $query->where('campaign_contacts.campaign_id', $campaignId))
            ->when($channel !== '', fn (Builder $query): Builder => $query->where('campaigns.channel', $channel))
            ->select([
                'campaigns.reference as campaign_reference',
                'campaigns.channel as campaign_channel',
                'campaign_contacts.status as outcome',
                'campaign_contacts.attempts',
                'campaign_contacts.cost',
                'campaign_contacts.sent_at',
                'campaign_contacts.delivered_at',
                'campaign_contacts.error_message',
                'campaign_contacts.recipient_snapshot',
                'contacts.first_name',
                'contacts.last_name',
                'contacts.phone',
                'contacts.email',
            ])
            ->orderBy('campaign_contacts.id')
            ->cursor();

        foreach ($rows as $row) {
            $snapshot = json_decode((string) $row->recipient_snapshot, true);

            yield [
                $row->campaign_reference,
                (string) $row->campaign_channel,
                $this->name($row->first_name, $row->last_name) ?? ($snapshot['name'] ?? ''),
                $row->phone ?? ($snapshot['phone'] ?? null),
                $row->email ?? ($snapshot['email'] ?? null),
                (string) $row->outcome,
                (int) $row->attempts,
                round((float) $row->cost, 4),
                $row->sent_at,
                $row->delivered_at,
                $row->error_message,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Generator<int, list<string|int|float|null>>
     */
    protected function voiceRows(ReportScope $scope, ReportPeriod $period, array $filters): Generator
    {
        $campaignId = (int) ($filters['campaign'] ?? 0);

        $rows = $scope->voice()
            ->leftJoin('campaign_contacts', 'campaign_contacts.id', '=', 'voice_calls.campaign_contact_id')
            ->leftJoin('contacts', 'contacts.id', '=', 'campaign_contacts.contact_id')
            ->whereBetween('voice_calls.dialled_at', [$period->from, $period->to])
            ->when($campaignId > 0, fn (Builder $query): Builder => $query->where('voice_calls.campaign_id', $campaignId))
            ->select([
                'voice_calls.reference',
                'voice_calls.to_number',
                'voice_calls.from_number',
                'voice_calls.status',
                'voice_calls.dtmf_digits',
                'voice_calls.attempts',
                'voice_calls.dialled_at',
                'voice_calls.duration_seconds',
                'voice_calls.cost',
                'voice_calls.error_message',
                'contacts.first_name',
                'contacts.last_name',
            ])
            ->orderBy('voice_calls.id')
            ->cursor();

        foreach ($rows as $row) {
            yield [
                $row->reference,
                $this->name($row->first_name, $row->last_name) ?? $row->to_number,
                $row->from_number,
                $this->text($row->status),
                $row->dtmf_digits,
                (int) $row->attempts,
                $row->dialled_at,
                $row->duration_seconds,
                round((float) $row->cost, 4),
                $row->error_message,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Generator<int, list<string|int|float|null>>
     */
    protected function smsRows(ReportScope $scope, ReportPeriod $period, array $filters): Generator
    {
        $campaignId = (int) ($filters['campaign'] ?? 0);

        $rows = $scope->sms()
            ->leftJoin('campaign_contacts', 'campaign_contacts.id', '=', 'sms_messages.campaign_contact_id')
            ->leftJoin('contacts', 'contacts.id', '=', 'campaign_contacts.contact_id')
            ->whereBetween('sms_messages.created_at', [$period->from, $period->to])
            ->when($campaignId > 0, fn (Builder $query): Builder => $query->where('sms_messages.campaign_id', $campaignId))
            ->select([
                'sms_messages.reference',
                'sms_messages.to_number',
                'sms_messages.from_number',
                'sms_messages.status',
                'sms_messages.segments',
                'sms_messages.attempts',
                'sms_messages.submitted_at',
                'sms_messages.delivered_at',
                'sms_messages.cost',
                'sms_messages.error_message',
                'contacts.first_name',
                'contacts.last_name',
            ])
            ->orderBy('sms_messages.id')
            ->cursor();

        foreach ($rows as $row) {
            yield [
                $row->reference,
                $this->name($row->first_name, $row->last_name) ?? $row->to_number,
                $row->from_number,
                $this->text($row->status),
                (int) $row->segments,
                (int) $row->attempts,
                $row->submitted_at,
                $row->delivered_at,
                round((float) $row->cost, 4),
                $row->error_message,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Generator<int, list<string|int|float|null>>
     */
    protected function emailRows(ReportScope $scope, ReportPeriod $period, array $filters): Generator
    {
        $campaignId = (int) ($filters['campaign'] ?? 0);

        $rows = $scope->email()
            ->leftJoin('campaign_contacts', 'campaign_contacts.id', '=', 'email_messages.campaign_contact_id')
            ->leftJoin('contacts', 'contacts.id', '=', 'campaign_contacts.contact_id')
            ->whereBetween('email_messages.created_at', [$period->from, $period->to])
            ->when($campaignId > 0, fn (Builder $query): Builder => $query->where('email_messages.campaign_id', $campaignId))
            ->select([
                'email_messages.reference',
                'email_messages.to_address',
                'email_messages.from_address',
                'email_messages.subject',
                'email_messages.status',
                'email_messages.attempts',
                'email_messages.submitted_at',
                'email_messages.delivered_at',
                'email_messages.cost',
                'email_messages.error_message',
                'contacts.first_name',
                'contacts.last_name',
            ])
            ->orderBy('email_messages.id')
            ->cursor();

        foreach ($rows as $row) {
            yield [
                $row->reference,
                $this->name($row->first_name, $row->last_name) ?? $row->to_address,
                $row->from_address,
                $row->subject,
                $this->text($row->status),
                (int) $row->attempts,
                $row->submitted_at,
                $row->delivered_at,
                round((float) $row->cost, 4),
                $row->error_message,
            ];
        }
    }

    /**
     * @return Generator<int, list<string|int|float|null>>
     */
    protected function revenueRows(ReportScope $scope, ReportPeriod $period): Generator
    {
        $rows = $scope->payments()
            ->join('clients', 'clients.id', '=', 'payments.client_id')
            ->whereBetween('payments.created_at', [$period->from, $period->to])
            ->select([
                'payments.reference',
                'clients.name as client_name',
                'payments.provider',
                'payments.status',
                'payments.currency',
                'payments.amount',
                'payments.amount_paid',
                'payments.paid_at',
                'payments.created_at',
            ])
            ->orderBy('payments.id')
            ->cursor();

        foreach ($rows as $row) {
            yield [
                $row->reference,
                $row->client_name,
                $row->provider,
                $this->text($row->status),
                $row->currency,
                round((float) $row->amount, 2),
                round((float) $row->amount_paid, 2),
                $row->paid_at,
                $row->created_at,
            ];
        }
    }

    /**
     * Join a first and last name, or null when the contact has neither.
     */
    protected function name(?string $first, ?string $last): ?string
    {
        $name = trim((string) $first.' '.(string) $last);

        return $name === '' ? null : $name;
    }

    /**
     * Turn a snake_case metric key into a heading.
     */
    protected function heading(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Read a cell the model may have cast to an enum.
     *
     * The row builders hydrate models, so a status column arrives as an enum
     * and cannot be written to the CSV until it is read back to its value.
     */
    protected function text(mixed $value): string
    {
        return match (true) {
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            default => (string) $value,
        };
    }
}
