<?php

use App\Enums\Channel;
use App\Enums\ReportExportStatus;
use App\Enums\ReportType;
use App\Jobs\GenerateReportExport;
use App\Mail\ScheduledReportMail;
use App\Models\Campaign;
use App\Models\ReportExport;
use App\Models\SmsMessage;
use App\Services\Reporting\ReportExportManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

it('writes the csv an export promises', function (): void {
    [$client] = clientWithWallet();

    $campaign = Campaign::factory()->for($client)->create(['channel' => Channel::Sms]);
    SmsMessage::factory()->forCampaign($campaign)->delivered()->create();

    $export = ReportExport::factory()->for($client)->create([
        'report' => ReportType::Sms,
        'file_name' => 'omnicast-sms-test.csv',
        'period_start' => now()->subDay()->toDateString(),
        'period_end' => now()->toDateString(),
    ]);

    (new GenerateReportExport($export->id))->handle(app(ReportExportManager::class));

    $export->refresh();

    expect($export->status)->toBe(ReportExportStatus::Completed)
        ->and($export->rows)->toBeGreaterThan(0)
        ->and($export->size_bytes)->toBeGreaterThan(0)
        ->and($export->completed_at)->not->toBeNull();

    Storage::disk('local')->assertExists('reports/omnicast-sms-test.csv');

    expect((string) Storage::disk('local')->get('reports/omnicast-sms-test.csv'))
        ->toContain('Reference,Recipient,From,Status')
        ->toContain(',delivered,');
});

it('marks an export failed when its file cannot be written', function (): void {
    [$client] = clientWithWallet();

    $export = ReportExport::factory()->for($client)->create(['report' => ReportType::Overview]);

    config()->set('reports.exports.disk', 'missing-report-disk');

    expect(fn () => (new GenerateReportExport($export->id))->handle(app(ReportExportManager::class)))
        ->toThrow(InvalidArgumentException::class);

    $export->refresh();

    expect($export->status)->toBe(ReportExportStatus::Failed)
        ->and($export->error_message)->not->toBeNull();
});

it('mails the scheduled report once per period', function (): void {
    Mail::fake();

    config()->set('reports.scheduled.enabled', true);
    config()->set('reports.scheduled.frequency', 'weekly');
    config()->set('reports.scheduled.recipients', ['ops@omnicast.test']);

    clientWithWallet();

    $this->artisan('reports:send-scheduled')->assertSuccessful();

    Mail::assertSent(ScheduledReportMail::class, 2);

    expect(ReportExport::query()->where('source', 'scheduled')->count())->toBe(2)
        ->and(ReportExport::query()->whereNull('emailed_at')->count())->toBe(0);

    Mail::fake();

    $this->artisan('reports:send-scheduled')->assertSuccessful();

    Mail::assertNothingSent();

    expect(ReportExport::query()->count())->toBe(2);
});

it('stays quiet while scheduled reports are switched off', function (): void {
    Mail::fake();

    config()->set('reports.scheduled.enabled', false);

    clientWithWallet();

    $this->artisan('reports:send-scheduled')->assertSuccessful();

    Mail::assertNothingSent();

    expect(ReportExport::query()->count())->toBe(0);
});
