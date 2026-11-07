<?php

use App\Enums\Channel;
use App\Enums\TemplateStatus;
use App\Models\Client;
use App\Models\MessageTemplate;
use App\Models\TemplateCategory;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    [$this->client, , $this->user] = clientWithWallet();

    $this->category = TemplateCategory::factory()->create(['name' => 'Finance & Collections']);
});

it('lists the catalogue, the tenant copies and the operator favourites', function (): void {
    $own = MessageTemplate::factory()
        ->forClient($this->client)
        ->inCategory($this->category)
        ->create(['name' => 'Own reminder']);

    $catalogue = MessageTemplate::factory()
        ->inCategory($this->category)
        ->create(['name' => 'Catalogue reminder']);

    MessageTemplate::factory()
        ->forClient(Client::factory()->create())
        ->create(['name' => 'Foreign reminder']);

    $archived = MessageTemplate::factory()
        ->status(TemplateStatus::Archived)
        ->create(['name' => 'Archived reminder']);

    $own->favouritedBy()->attach($this->user);

    $this->actingAs($this->user)
        ->get(route('templates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/index')
            ->has('templates.data', 3)
            ->has('categories')
            ->where('filters.search', '')
            ->where('counts.all', 3)
            ->where('counts.sms', 3)
            ->where('stats.total', 3)
            ->where('stats.mine', 1)
            ->where('stats.favourites', 1)
            ->where('can.create', true)
            ->where('templates.data', function ($rows) use ($own, $catalogue, $archived): bool {
                $ids = $rows->pluck('id')->sort()->values()->all();
                $expected = [$own->id, $catalogue->id, $archived->id];

                sort($expected);

                return $ids === $expected;
            }));
});

it('filters the library by category, channel and ownership', function (): void {
    $voice = MessageTemplate::factory()
        ->channel(Channel::Voice)
        ->inCategory($this->category)
        ->create(['name' => 'Voice reminder']);

    MessageTemplate::factory()
        ->inCategory($this->category)
        ->create(['name' => 'SMS reminder']);

    $own = MessageTemplate::factory()
        ->forClient($this->client)
        ->inCategory(TemplateCategory::factory()->forClient($this->client)->create())
        ->create(['name' => 'Own reminder']);

    $this->actingAs($this->user)
        ->get(route('templates.index', ['category' => $this->category->slug, 'channel' => Channel::Voice->value]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('templates.data', 1)
            ->where('templates.data.0.id', $voice->id));

    $this->actingAs($this->user)
        ->get(route('templates.index', ['mine' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('templates.data', 1)
            ->where('templates.data.0.id', $own->id));

    $this->actingAs($this->user)
        ->get(route('templates.index', ['search' => 'voice']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('templates.data', 1)
            ->where('templates.data.0.id', $voice->id));
});

it('shows a new template the categories the tenant may file it under', function (): void {
    TemplateCategory::factory()->forClient($this->client)->create(['name' => 'Private']);
    TemplateCategory::factory()->forClient(Client::factory()->create())->create(['name' => 'Foreign']);

    $this->actingAs($this->user)
        ->get(route('templates.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/create')
            ->has('categories', 2)
            ->where('categories', fn ($categories): bool => $categories->pluck('name')->sort()->values()->all() === ['Finance & Collections', 'Private'])
            ->has('channels')
            ->has('statuses'));
});

it('saves a tenant template under the signed-in account', function (): void {
    $foreign = Client::factory()->create();

    $this->actingAs($this->user)
        ->post(route('templates.store'), [
            'name' => 'Rent reminder',
            'channel' => Channel::Sms->value,
            'language' => 'en',
            'template_category_id' => $this->category->id,
            'body' => 'Dear {{first_name}}, your rent is due on {{due_date}}.',
            'status' => TemplateStatus::Active->value,
            'client_id' => $foreign->id,
        ])
        ->assertRedirect(route('templates.index'));

    $template = MessageTemplate::query()->where('name', 'Rent reminder')->sole();

    expect($template->client_id)->toBe($this->client->id)
        ->and($template->status)->toBe(TemplateStatus::Active)
        ->and($template->created_by)->toBe($this->user->id)
        ->and($template->variableTags())->toBe(['{{first_name}}', '{{due_date}}']);
});

it('opens a tenant template in the editor and refuses the catalogue copy', function (): void {
    $own = MessageTemplate::factory()->forClient($this->client)->create(['name' => 'Own reminder']);
    $catalogue = MessageTemplate::factory()->create(['name' => 'Catalogue reminder']);

    $this->actingAs($this->user)
        ->get(route('templates.edit', $own))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/edit')
            ->where('template.id', $own->id)
            ->where('template.name', 'Own reminder'));

    $this->actingAs($this->user)
        ->get(route('templates.edit', $catalogue))
        ->assertForbidden();
});

it('copies a catalogue template into the tenant when it is customised', function (): void {
    $catalogue = MessageTemplate::factory()
        ->inCategory($this->category)
        ->create(['name' => 'Catalogue reminder']);

    $this->actingAs($this->user)
        ->post(route('templates.customise', $catalogue))
        ->assertRedirect();

    $copy = MessageTemplate::query()
        ->where('client_id', $this->client->id)
        ->where('origin_template_id', $catalogue->id)
        ->sole();

    expect($copy->name)->toBe('Catalogue reminder')
        ->and($copy->template_category_id)->toBe($this->category->id)
        ->and($copy->body)->toBe($catalogue->body);
});

it('toggles a template on the operator shortlist', function (): void {
    $catalogue = MessageTemplate::factory()->create(['name' => 'Catalogue reminder']);

    $this->actingAs($this->user)
        ->post(route('templates.favourite', $catalogue))
        ->assertRedirect();

    expect($catalogue->favouritedBy()->whereKey($this->user->id)->exists())->toBeTrue();

    $this->actingAs($this->user)
        ->get(route('templates.index', ['favourites' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('templates.data', 1)
            ->where('templates.data.0.is_favourite', true));

    $this->actingAs($this->user)
        ->post(route('templates.favourite', $catalogue));

    expect($catalogue->favouritedBy()->whereKey($this->user->id)->exists())->toBeFalse();
});
