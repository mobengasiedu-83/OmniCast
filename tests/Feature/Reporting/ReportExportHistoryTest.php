<?php

use App\Enums\ReportExportStatus;
use App\Enums\ReportType;
use App\Enums\Role as RoleEnum;
use App\Jobs\GenerateReportExport;
use App\Models\ReportExport;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Queue::fake();
    $this->withoutVite();
});

it('records the window an export was requested for', function (): void {
    [$client, , $user] = clientWithWallet();

    $this->actingAs($user)
        ->post(route('reports.exports.store'), [
            'report' => ReportType::Overview->value,
            'from' => '2026-01-01',
            'to' => '2026-01-31',
            'channel' => 'sms',
        ])
        ->assertRedirect();

    $export = ReportExport::query()->sole();

    expect($export->client_id)->toBe($client->id)
        ->and($export->requested_by)->toBe($user->id)
        ->and($export->report)->toBe(ReportType::Overview)
        ->and($export->status)->toBe(ReportExportStatus::Pending)
        ->and($export->source)->toBe('manual')
        ->and($export->period_start->toDateString())->toBe('2026-01-01')
        ->and($export->period_end->toDateString())->toBe('2026-01-31')
        ->and($export->filters['channel'])->toBe('sms')
        ->and($export->file_name)->toEndWith('.csv');

    Queue::assertPushed(GenerateReportExport::class);
});

it('lists only the exports the tenant asked for', function (): void {
    [$client, , $user] = clientWithWallet();
    [$otherClient] = clientWithWallet();

    $mine = ReportExport::factory()->for($client)->create();
    ReportExport::factory()->for($otherClient)->create();

    $this->actingAs($user)
        ->get(route('reports.exports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/exports')
            ->has('exports', 1)
            ->where('exports.0.id', $mine->id)
            ->has('reports', count(ReportType::clientReports())));
});

it('refuses a platform report to a client tenant', function (): void {
    [, , $user] = clientWithWallet();

    $this->actingAs($user)
        ->post(route('reports.exports.store'), [
            'report' => ReportType::Revenue->value,
        ])
        ->assertForbidden();

    expect(ReportExport::query()->count())->toBe(0);
});

it('refuses an export to a tenant user without the export permission', function (): void {
    [, , $user] = clientWithWallet(role: RoleEnum::ClientOperator);

    $this->actingAs($user)
        ->post(route('reports.exports.store'), [
            'report' => ReportType::Overview->value,
        ])
        ->assertForbidden();
});

it('lets the platform request an export across every client', function (): void {
    $admin = platformUser();

    $this->actingAs($admin)
        ->post(route('admin.reports.exports.store'), [
            'report' => ReportType::Executive->value,
        ])
        ->assertRedirect();

    $export = ReportExport::query()->sole();

    expect($export->client_id)->toBeNull()
        ->and($export->scope)->toBe('platform:all')
        ->and($export->report)->toBe(ReportType::Executive);

    $this->actingAs($admin)
        ->get(route('admin.reports.exports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/exports')
            ->has('exports', 1)
            ->has('reports', count([...ReportType::clientReports(), ...ReportType::platformReports()])));
});

it('never serves another tenant export file', function (): void {
    Storage::fake('local');

    [, , $user] = clientWithWallet();
    [$otherClient] = clientWithWallet();

    $foreign = ReportExport::factory()->for($otherClient)->completed()->create();
    Storage::disk('local')->put((string) $foreign->file_path, "reference,total\nSM-1,1\n");

    $this->actingAs($user)
        ->get(route('reports.exports.download', $foreign))
        ->assertNotFound();
});

it('streams a completed export to the tenant that owns it', function (): void {
    Storage::fake('local');

    [$client, , $user] = clientWithWallet();

    $export = ReportExport::factory()->for($client)->completed()->create();
    Storage::disk('local')->put((string) $export->file_path, "reference,total\nSM-1,1\n");

    $this->actingAs($user)
        ->get(route('reports.exports.download', $export))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('hides the download of an export that has not been generated yet', function (): void {
    Storage::fake('local');

    [$client, , $user] = clientWithWallet();

    $export = ReportExport::factory()->for($client)->create();

    $this->actingAs($user)
        ->get(route('reports.exports.download', $export))
        ->assertNotFound();
});
