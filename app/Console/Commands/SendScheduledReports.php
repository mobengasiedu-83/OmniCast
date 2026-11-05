<?php

namespace App\Console\Commands;

use App\Enums\ReportType;
use App\Enums\Role;
use App\Mail\ScheduledReportMail;
use App\Models\Client;
use App\Models\ReportExport;
use App\Services\Reporting\ReportExportManager;
use App\Services\Reporting\ReportPeriod;
use App\Services\Reporting\ReportScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Mails the recurring report for the period that just closed.
 *
 * The platform recipients get the executive summary; each active client's own
 * admins get that client's overview. Every export is created once per period by
 * its unique key, so running the command twice mails nothing new the second
 * time.
 */
class SendScheduledReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:send-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Email the scheduled weekly or monthly report';

    /**
     * Execute the console command.
     */
    public function handle(ReportExportManager $exports): int
    {
        if (! config('reports.scheduled.enabled')) {
            $this->components->warn('Scheduled reports are switched off (REPORTS_SCHEDULED_ENABLED).');

            return self::SUCCESS;
        }

        $frequency = (string) config('reports.scheduled.frequency', 'weekly');
        $period = $frequency === 'monthly'
            ? ReportPeriod::previousMonth()
            : ReportPeriod::previousWeek();

        $this->sendPlatformSummary($exports, $period);
        $this->sendClientOverviews($exports, $period);

        return self::SUCCESS;
    }

    /**
     * Mail the executive summary to the platform recipients.
     */
    protected function sendPlatformSummary(ReportExportManager $exports, ReportPeriod $period): void
    {
        $recipients = array_values((array) config('reports.scheduled.recipients', []));

        if ($recipients === []) {
            $this->components->warn('No platform recipients are configured for the scheduled report.');

            return;
        }

        $scope = new ReportScope(null, true);
        $export = $exports->schedule(ReportType::Executive, $scope, $period);

        $this->mail($export, $recipients, 'Platform summary');
    }

    /**
     * Mail each active client's admins their own overview.
     */
    protected function sendClientOverviews(ReportExportManager $exports, ReportPeriod $period): void
    {
        Client::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->chunkById(50, function ($clients) use ($exports, $period): void {
                foreach ($clients as $client) {
                    $recipients = $this->adminEmails($client);

                    if ($recipients === []) {
                        continue;
                    }

                    $export = $exports->schedule(
                        ReportType::Overview,
                        new ReportScope($client->id, false),
                        $period,
                    );

                    $this->mail($export, $recipients, $client->name);
                }
            });
    }

    /**
     * The client admins who receive the report.
     *
     * @return list<string>
     */
    protected function adminEmails(Client $client): array
    {
        return $client->users()
            ->whereHas('roles', fn ($query) => $query->where('name', Role::ClientAdmin->value))
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Attach the generated file and send it, once.
     *
     * @param  list<string>  $recipients
     */
    protected function mail(ReportExport $export, array $recipients, string $audience): void
    {
        if ($export->emailed_at !== null) {
            $this->components->twoColumnDetail($audience, 'already sent');

            return;
        }

        if (! $export->isReady()) {
            $this->components->twoColumnDetail($audience, 'export failed');

            return;
        }

        Mail::to($recipients)->send(new ScheduledReportMail($export));

        $export->forceFill(['emailed_at' => now()])->save();

        $this->components->twoColumnDetail($audience, count($recipients).' recipient(s)');
    }
}
