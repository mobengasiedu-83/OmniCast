<?php

use App\Enums\Role as RoleEnum;
use App\Enums\SupportTicketStatus;
use App\Mail\SupportTeamAlertMail;
use App\Mail\SupportTicketUpdateMail;
use App\Models\SupportArticle;
use App\Models\SupportCategory;
use App\Models\SupportTeam;
use App\Models\SupportTicket;
use App\Services\Support\SupportSettings;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();

    Mail::fake();
});

it('lists enquiries and tickets on their own screens', function (): void {
    SupportTicket::factory()->enquiry()->create(['subject' => 'Public question']);
    SupportTicket::factory()->create(['subject' => 'Client problem']);

    $this->actingAs(platformUser(RoleEnum::ComplianceOfficer))
        ->get(route('admin.support.enquiries.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/support/enquiries')
            ->has('enquiries.data', 1)
            ->where('enquiries.data.0.subject', 'Public question')
            ->where('stats.new', 1));

    $this->actingAs(platformUser(RoleEnum::ComplianceOfficer))
        ->get(route('admin.support.tickets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/support/tickets')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.subject', 'Client problem')
            ->where('stats.open', 1));
});

it('answers a ticket and stamps the first response', function (): void {
    $ticket = SupportTicket::factory()->create(['email' => 'client@example.com']);

    $this->actingAs(platformUser())
        ->post(route('admin.support.tickets.reply', $ticket->reference), [
            'body' => 'We found the stalled campaign and released it.',
        ])
        ->assertRedirect();

    $ticket->refresh();

    expect($ticket->status)->toBe(SupportTicketStatus::Open)
        ->and($ticket->first_responded_at)->not->toBeNull()
        ->and($ticket->messages()->count())->toBe(1);

    Mail::assertQueued(
        SupportTicketUpdateMail::class,
        fn (SupportTicketUpdateMail $mail): bool => $mail->hasTo('client@example.com'),
    );
});

it('keeps an internal note out of the requester’s inbox', function (): void {
    $ticket = SupportTicket::factory()->create(['email' => 'client@example.com']);

    $this->actingAs(platformUser())
        ->post(route('admin.support.tickets.reply', $ticket->reference), [
            'body' => 'Waiting on the provider before we answer.',
            'internal' => '1',
        ])
        ->assertRedirect();

    expect($ticket->messages()->first()->is_internal)->toBeTrue();

    Mail::assertNotQueued(SupportTicketUpdateMail::class);
});

it('emails the requester when a conversation is resolved', function (): void {
    $ticket = SupportTicket::factory()->create(['email' => 'client@example.com']);

    $this->actingAs(platformUser())
        ->put(route('admin.support.tickets.update', $ticket->reference), [
            'status' => SupportTicketStatus::Resolved->value,
            'priority' => $ticket->priority->value,
        ])
        ->assertRedirect();

    $ticket->refresh();

    expect($ticket->status)->toBe(SupportTicketStatus::Resolved)
        ->and($ticket->resolved_at)->not->toBeNull();

    Mail::assertQueued(SupportTicketUpdateMail::class);
});

it('routes a ticket to a team, a category and an owner', function (): void {
    $team = SupportTeam::factory()->create(['name' => 'Technical']);
    $category = SupportCategory::factory()->create(['name' => 'API']);
    $agent = platformUser();

    $ticket = SupportTicket::factory()->urgent()->create();

    $this->actingAs(platformUser())
        ->put(route('admin.support.tickets.update', $ticket->reference), [
            'status' => SupportTicketStatus::Open->value,
            'priority' => 'high',
            'support_team_id' => $team->id,
            'support_category_id' => $category->id,
            'assigned_to' => $agent->id,
        ])
        ->assertRedirect();

    $ticket->refresh();

    expect($ticket->support_team_id)->toBe($team->id)
        ->and($ticket->support_category_id)->toBe($category->id)
        ->and($ticket->assigned_to)->toBe($agent->id)
        ->and($ticket->priority->value)->toBe('high');
});

it('adds a category the forms then offer', function (): void {
    $team = SupportTeam::factory()->create();

    $this->actingAs(platformUser())
        ->post(route('admin.support.categories.store'), [
            'name' => 'Wallet top-up',
            'description' => 'Funding and refunds.',
            'audience' => 'both',
            'support_team_id' => $team->id,
            'default_priority' => 'high',
            'is_active' => true,
            'sort_order' => 3,
        ])
        ->assertRedirect();

    $category = SupportCategory::query()->sole();

    expect($category->slug)->toBe('wallet-top-up')
        ->and($category->support_team_id)->toBe($team->id)
        ->and($category->default_priority->value)->toBe('high');

    $this->get(route('contact.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.0.slug', 'wallet-top-up'));
});

it('refuses to delete a category that has conversations', function (): void {
    $category = SupportCategory::factory()->create();

    SupportTicket::factory()->category($category)->create();

    $this->actingAs(platformUser())
        ->delete(route('admin.support.categories.destroy', $category))
        ->assertRedirect();

    expect(SupportCategory::query()->whereKey($category->id)->exists())->toBeTrue();
});

it('deletes a category nothing was filed under', function (): void {
    $category = SupportCategory::factory()->create();

    $this->actingAs(platformUser())
        ->delete(route('admin.support.categories.destroy', $category))
        ->assertRedirect();

    expect(SupportCategory::query()->whereKey($category->id)->exists())->toBeFalse();
});

it('adds and saves a team', function (): void {
    $this->actingAs(platformUser())
        ->post(route('admin.support.teams.store'), [
            'name' => 'Escalations',
            'email' => 'escalations@example.com',
            'description' => 'Anything the first line cannot settle.',
            'is_active' => true,
            'sort_order' => 5,
        ])
        ->assertRedirect();

    $team = SupportTeam::query()->sole();

    $this->actingAs(platformUser())
        ->put(route('admin.support.teams.update', $team), [
            'name' => 'Escalations desk',
            'email' => 'escalations@example.com',
            'is_active' => false,
            'sort_order' => 5,
        ])
        ->assertRedirect();

    $team->refresh();

    expect($team->name)->toBe('Escalations desk')
        ->and($team->is_active)->toBeFalse();
});

it('saves the contact details the contact page publishes', function (): void {
    $this->actingAs(platformUser())
        ->put(route('admin.support.notifications.update'), [
            'contact_email' => 'hello@example.com',
            'support_email' => 'desk@example.com',
            'sales_email' => '',
            'phone' => '+233 20 000 0000',
            'whatsapp' => '',
            'office_address' => '12 Independence Avenue, Accra',
            'opening_hours' => 'Mon–Fri, 08:00–17:00',
            'response_time' => 'Within one business day.',
            'status_url' => 'https://status.example.com',
            'acknowledge_enquiries' => true,
            'notify_on_status_change' => false,
            'notify_team' => true,
        ])
        ->assertRedirect();

    $settings = app(SupportSettings::class);

    expect($settings->supportEmail())->toBe('desk@example.com')
        ->and($settings->phone())->toBe('+233 20 000 0000')
        ->and($settings->notifiesOnStatusChange())->toBeFalse()
        ->and($settings->acknowledgesEnquiries())->toBeTrue();
});

it('writes an article and publishes it to the help centre', function (): void {
    $this->actingAs(platformUser())
        ->post(route('admin.support.knowledge-base.store'), [
            'title' => 'Read a delivery report',
            'category' => 'Reporting',
            'excerpt' => 'Which recipients were delivered, failed or suppressed.',
            'body' => "## Where to look\n\nOpen Reports then Campaigns.",
            'is_published' => true,
            'is_featured' => true,
            'sort_order' => 1,
        ])
        ->assertRedirect();

    $article = SupportArticle::query()->sole();

    expect($article->slug)->toBe('read-a-delivery-report')
        ->and($article->is_published)->toBeTrue()
        ->and($article->published_at)->not->toBeNull();

    $this->get(route('help.show', $article->slug))->assertOk();
});

it('reports volumes and response times', function (): void {
    SupportTicket::factory()->enquiry()->create();
    SupportTicket::factory()->create([
        'first_responded_at' => now()->subHours(2),
        'created_at' => now()->subHours(6),
        'status' => SupportTicketStatus::Resolved,
        'resolved_at' => now()->subHour(),
    ]);

    $this->actingAs(platformUser())
        ->get(route('admin.support.reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/support/reports')
            ->where('totals.enquiries', 1)
            ->where('totals.tickets', 1)
            ->where('window.first_response_hours', fn ($value): bool => is_numeric($value) && $value > 0)
            ->has('by_status', 5)
            ->has('by_team'));
});

it('lets a finance officer read the register but not change it', function (): void {
    $ticket = SupportTicket::factory()->create();

    $this->actingAs(platformUser(RoleEnum::FinanceOfficer))
        ->get(route('admin.support.tickets.index'))
        ->assertOk();

    $this->actingAs(platformUser(RoleEnum::FinanceOfficer))
        ->put(route('admin.support.tickets.update', $ticket->reference), [
            'status' => SupportTicketStatus::Open->value,
            'priority' => $ticket->priority->value,
        ])
        ->assertForbidden();
});

it('keeps a client user out of the admin register', function (): void {
    [, , $user] = clientWithWallet();

    $this->actingAs($user)
        ->get(route('admin.support.tickets.index'))
        ->assertForbidden();
});

it('alerts the owning team when a client replies', function (): void {
    $team = SupportTeam::factory()->create(['email' => 'desk@example.com']);
    $category = SupportCategory::factory()->forTeam($team)->create();

    [, , $user] = clientWithWallet();
    $ticket = SupportTicket::factory()->forClient($user->client)->category($category)->create();

    $this->actingAs($user)
        ->post(route('support.tickets.reply', $ticket->reference), [
            'body' => 'Any news on this one?',
        ])
        ->assertRedirect();

    Mail::assertQueued(
        SupportTeamAlertMail::class,
        fn (SupportTeamAlertMail $mail): bool => $mail->hasTo('desk@example.com'),
    );
});
