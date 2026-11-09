<?php

use App\Enums\SupportTicketStatus;
use App\Enums\SupportTicketType;
use App\Models\SupportCategory;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use App\Services\Support\SupportDesk;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

/**
 * A ticket category the client form offers.
 */
function ticketCategory(): SupportCategory
{
    return SupportCategory::factory()->create([
        'name' => 'Campaign delivery',
        'slug' => 'campaign-delivery',
        'audience' => 'both',
    ]);
}

it('shows the support overview for a client', function (): void {
    [, , $user] = clientWithWallet();

    SupportTicket::factory()->forClient($user->client)->create();

    $this->actingAs($user)
        ->get(route('support.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('support/index')
            ->where('stats.total', 1)
            ->where('stats.open', 1));
});

it('opens a ticket from the client portal', function (): void {
    [, , $user] = clientWithWallet();
    $category = ticketCategory();

    $this->actingAs($user)
        ->post(route('support.tickets.store'), [
            'category' => $category->slug,
            'subject' => 'Nothing was delivered',
            'description' => 'The campaign shows 400 queued messages and no deliveries since yesterday.',
        ])
        ->assertRedirect();

    $ticket = SupportTicket::query()->sole();

    expect($ticket->client_id)->toBe($user->client_id)
        ->and($ticket->user_id)->toBe($user->id)
        ->and($ticket->reference)->toStartWith('TKT-')
        ->and($ticket->isTicket())->toBeTrue()
        ->and($ticket->status)->toBe(SupportTicketStatus::New)
        ->and($ticket->messages()->count())->toBe(1);
});

it('lists only the organisation’s own tickets', function (): void {
    [, , $user] = clientWithWallet();
    [$otherClient] = clientWithWallet();

    SupportTicket::factory()->forClient($user->client)->create(['subject' => 'Mine']);
    SupportTicket::factory()->forClient($otherClient)->create(['subject' => 'Theirs']);

    $this->actingAs($user)
        ->get(route('support.tickets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('support/tickets/index')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.subject', 'Mine'));
});

it('refuses to show another organisation’s ticket', function (): void {
    [, , $user] = clientWithWallet();
    [$otherClient] = clientWithWallet();

    $foreign = SupportTicket::factory()->forClient($otherClient)->create();

    $this->actingAs($user)
        ->get(route('support.tickets.show', $foreign->reference))
        ->assertForbidden();
});

it('hides internal notes from the requester', function (): void {
    [, , $user] = clientWithWallet();

    $ticket = SupportTicket::factory()->forClient($user->client)->create();

    SupportTicketMessage::factory()->for($ticket, 'ticket')->create([
        'body' => 'Visible reply',
        'source' => 'agent',
    ]);
    SupportTicketMessage::factory()->for($ticket, 'ticket')->internal()->create([
        'body' => 'Secret note',
    ]);

    $this->actingAs($user)
        ->get(route('support.tickets.show', $ticket->reference))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('support/tickets/show')
            ->has('messages', 1)
            ->where('messages.0.body', 'Visible reply'));
});

it('reopens a settled ticket when the requester replies', function (): void {
    [, , $user] = clientWithWallet();

    $ticket = SupportTicket::factory()->forClient($user->client)->resolved()->create();

    $this->actingAs($user)
        ->post(route('support.tickets.reply', $ticket->reference), [
            'body' => 'It happened again this morning.',
        ])
        ->assertRedirect();

    $ticket->refresh();

    expect($ticket->status)->toBe(SupportTicketStatus::Open)
        ->and($ticket->resolved_at)->toBeNull()
        ->and($ticket->messages()->count())->toBe(1);
});

it('closes a ticket from the client portal', function (): void {
    [, , $user] = clientWithWallet();

    $ticket = SupportTicket::factory()->forClient($user->client)->answered()->create();

    $this->actingAs($user)
        ->post(route('support.tickets.close', $ticket->reference))
        ->assertRedirect();

    $ticket->refresh();

    expect($ticket->status)->toBe(SupportTicketStatus::Closed)
        ->and($ticket->closed_at)->not->toBeNull();
});

it('streams an attachment only to the owning organisation', function (): void {
    Storage::fake('local');

    [, , $user] = clientWithWallet();
    [, , $otherUser] = clientWithWallet();

    $ticket = SupportTicket::factory()->forClient($user->client)->create();
    Storage::disk('local')->put('support/report.csv', 'id,status');

    $attachment = SupportTicketAttachment::factory()->for($ticket, 'ticket')->create([
        'path' => 'support/report.csv',
        'original_name' => 'report.csv',
        'mime_type' => 'text/csv',
    ]);

    $this->actingAs($user)
        ->get(route('support.tickets.attachments.download', [$ticket->reference, $attachment->id]))
        ->assertOk();

    $this->actingAs($otherUser)
        ->get(route('support.tickets.attachments.download', [$ticket->reference, $attachment->id]))
        ->assertForbidden();
});

it('serves the knowledge base and the contact directory to a signed-in client', function (): void {
    [, , $user] = clientWithWallet();

    $this->actingAs($user)
        ->get(route('support.knowledge-base.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('support/knowledge-base/index'));

    $this->actingAs($user)
        ->get(route('support.contact'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('support/contact'));
});

it('writes a reference the team can quote', function (): void {
    $desk = app(SupportDesk::class);

    $first = $desk->reference(SupportTicketType::Ticket);
    $second = $desk->reference(SupportTicketType::Ticket);

    expect($first)->toStartWith('TKT-')
        ->and($first)->not->toBe($second)
        ->and($desk->reference(SupportTicketType::Enquiry))->toStartWith('ENQ-');
});
