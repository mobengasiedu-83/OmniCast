<?php

use App\Enums\Channel;
use App\Enums\TemplateStatus;
use App\Models\Client;
use App\Models\MessageTemplate;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->admin = platformUser();

    [$this->client, , $this->tenantUser] = clientWithWallet();
});

it('shows the platform catalogue without the tenant rows', function (): void {
    MessageTemplate::factory()->create(['name' => 'Catalogue reminder']);

    MessageTemplate::factory()
        ->forClient($this->client)
        ->create(['name' => 'Tenant reminder']);

    $this->actingAs($this->admin)
        ->get(route('admin.templates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/templates/index')
            ->has('templates.data', 1)
            ->where('templates.data.0.name', 'Catalogue reminder')
            ->where('stats.total', 1)
            ->where('stats.active', 1)
            ->where('can.create', true)
            ->where('can.approve', true));
});

it('keeps tenant users out of the catalogue, the review queue and the usage report', function (): void {
    $this->actingAs($this->tenantUser)
        ->get(route('admin.templates.index'))
        ->assertForbidden();

    $this->actingAs($this->tenantUser)
        ->get(route('admin.template-approvals.index'))
        ->assertForbidden();

    $this->actingAs($this->tenantUser)
        ->get(route('admin.template-usage.index'))
        ->assertForbidden();
});

it('adds a template to the catalogue as platform property', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.templates.store'), [
            'name' => 'Catalogue welcome',
            'channel' => Channel::Sms->value,
            'language' => 'en',
            'body' => 'Welcome to {{company_name}}, {{first_name}}!',
            'status' => TemplateStatus::Active->value,
            'client_id' => $this->client->id,
        ])
        ->assertRedirect(route('admin.templates.index'));

    $template = MessageTemplate::query()->where('name', 'Catalogue welcome')->sole();

    expect($template->client_id)->toBeNull()
        ->and($template->status)->toBe(TemplateStatus::Active)
        ->and($template->approved_by)->toBe($this->admin->id)
        ->and($template->approved_at)->not->toBeNull();
});

it('lists only tenant templates waiting for review and approves them', function (): void {
    $pending = MessageTemplate::factory()
        ->forClient($this->client)
        ->pending()
        ->create(['name' => 'Tenant reminder']);

    MessageTemplate::factory()->forClient($this->client)->create(['name' => 'Already active']);
    MessageTemplate::factory()->pending()->create(['name' => 'Catalogue draft']);

    $this->actingAs($this->admin)
        ->get(route('admin.template-approvals.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/templates/approvals')
            ->has('templates.data', 1)
            ->where('templates.data.0.id', $pending->id)
            ->where('stats.pending', 1)
            ->where('can.approve', true));

    $this->actingAs($this->admin)
        ->post(route('admin.template-approvals.approve', $pending))
        ->assertRedirect();

    $pending->refresh();

    expect($pending->status)->toBe(TemplateStatus::Active)
        ->and($pending->approved_by)->toBe($this->admin->id)
        ->and($pending->approved_at)->not->toBeNull();
});

it('returns a template to its author with a reason', function (): void {
    $pending = MessageTemplate::factory()
        ->forClient($this->client)
        ->pending()
        ->create(['name' => 'Tenant reminder']);

    $this->actingAs($this->admin)
        ->post(route('admin.template-approvals.reject', $pending), [])
        ->assertSessionHasErrors('reason');

    expect($pending->refresh()->status)->toBe(TemplateStatus::Pending);

    $this->actingAs($this->admin)
        ->post(route('admin.template-approvals.reject', $pending), [
            'reason' => 'Add an opt-out line before this can go live.',
        ])
        ->assertRedirect();

    $pending->refresh();

    expect($pending->status)->toBe(TemplateStatus::Rejected)
        ->and($pending->rejection_reason)->toBe('Add an opt-out line before this can go live.');
});

it('ranks the usage report by uses and filters by owner', function (): void {
    MessageTemplate::factory()->create(['name' => 'Quiet catalogue', 'usage_count' => 2]);
    MessageTemplate::factory()
        ->forClient($this->client)
        ->create(['name' => 'Busy tenant copy', 'usage_count' => 9]);

    $this->actingAs($this->admin)
        ->get(route('admin.template-usage.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/templates/usage')
            ->has('templates.data', 2)
            ->where('templates.data.0.name', 'Busy tenant copy')
            ->where('templates.data.0.ownership', 'Client')
            ->where('templates.data.1.name', 'Quiet catalogue')
            ->where('templates.data.1.ownership', 'System')
            ->where('stats.uses', 11)
            ->where('stats.templates_used', 2));

    $this->actingAs($this->admin)
        ->get(route('admin.template-usage.index', ['ownership' => 'system']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('templates.data', 1)
            ->where('templates.data.0.name', 'Quiet catalogue'));

    MessageTemplate::factory()
        ->forClient(Client::factory()->create())
        ->create(['name' => 'Other tenant copy', 'usage_count' => 4]);

    $this->actingAs($this->admin)
        ->get(route('admin.template-usage.index', ['ownership' => 'tenant']))
        ->assertInertia(fn (Assert $page) => $page->has('templates.data', 2));
});
