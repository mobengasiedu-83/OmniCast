<?php

use App\Enums\Channel;
use App\Enums\TemplateStatus;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\MessageTemplate;
use App\Models\SenderId;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Queue::fake();

    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 20.0, smsPrice: 0.05);

    $this->sender = SenderId::factory()->for($this->client)->sms()->approved()->create();

    $this->list = ContactList::factory()->forClient($this->client)->create();
    $this->list->contacts()->attach(
        Contact::factory()->count(2)->forClient($this->client)->create()->pluck('id'),
        ['added_at' => now()],
    );

    $this->payload = fn (array $attributes = []): array => [
        'name' => 'Statement reminder',
        'channel' => Channel::Sms->value,
        'contact_list_id' => $this->list->id,
        'sender_id' => $this->sender->id,
        'sms_message' => 'Hello {{first_name}}, your statement is ready.',
        'schedule_type' => 'now',
        ...$attributes,
    ];
});

it('offers the editor only the catalogue and tenant copies still in use', function (): void {
    $catalogue = MessageTemplate::factory()->create(['name' => 'Catalogue reminder']);
    $own = MessageTemplate::factory()->forClient($this->client)->create(['name' => 'Own reminder']);

    MessageTemplate::factory()->forClient(Client::factory()->create())->create(['name' => 'Foreign reminder']);
    MessageTemplate::factory()->create(['name' => 'Archived reminder'])->update([
        'status' => TemplateStatus::Archived,
    ]);
    MessageTemplate::factory()->create(['name' => 'Unreviewed reminder'])->update([
        'status' => TemplateStatus::Pending,
    ]);

    $this->actingAs($this->user)
        ->get(route('campaigns.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/create')
            ->has('templates', 2)
            ->where('templates.0.id', $catalogue->id)
            ->where('templates.0.is_system', true)
            ->where('templates.1.id', $own->id)
            ->where('templates.1.is_system', false)
            ->where('templates.0.variables', ['{{first_name}}', '{{company_name}}'])
        );
});

it('prefills the editor with the copy of the template the operator opened', function (): void {
    $template = MessageTemplate::factory()->channel(Channel::Email)->create([
        'name' => 'Statement email',
    ]);

    $this->actingAs($this->user)
        ->get(route('campaigns.create', ['template' => $template->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/create')
            ->where('initial.channel', Channel::Email->value)
            ->where('initial.message_template_id', (string) $template->id)
            ->where('initial.email_subject', $template->subject)
            ->where('initial.email_body', $template->body)
        );
});

it('ignores a template another tenant owns when prefilling the editor', function (): void {
    $foreign = MessageTemplate::factory()
        ->forClient(Client::factory()->create())
        ->create(['name' => 'Foreign reminder']);

    $this->actingAs($this->user)
        ->get(route('campaigns.create', ['template' => $foreign->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/create')
            ->where('initial', [])
        );
});

it('records the template a campaign starts from', function (): void {
    $template = MessageTemplate::factory()->create(['name' => 'Catalogue reminder']);

    $this->actingAs($this->user)
        ->post(route('campaigns.store'), ($this->payload)([
            'message_template_id' => $template->id,
        ]))
        ->assertSessionHasNoErrors();

    $campaign = Campaign::query()->sole();

    expect($campaign->message_template_id)->toBe($template->id)
        ->and($template->refresh()->usage_count)->toBe(1);
});

it('records the template only when an edited campaign switches to another', function (): void {
    $original = MessageTemplate::factory()->create(['name' => 'Catalogue reminder']);
    $replacement = MessageTemplate::factory()->create(['name' => 'Follow-up reminder']);

    $campaign = Campaign::factory()->forClient($this->client)->create([
        'name' => 'Statement reminder',
        'contact_list_id' => $this->list->id,
        'sender_id' => $this->sender->id,
        'sms_message' => 'Hello {{first_name}}, your statement is ready.',
        'message_template_id' => $original->id,
    ]);

    $payload = ($this->payload)(['message_template_id' => $original->id]);

    $this->actingAs($this->user)
        ->put(route('campaigns.update', $campaign), $payload)
        ->assertSessionHasNoErrors();

    expect($original->refresh()->usage_count)->toBe(0);

    $this->actingAs($this->user)
        ->put(route('campaigns.update', $campaign), [
            ...$payload,
            'message_template_id' => $replacement->id,
        ])
        ->assertSessionHasNoErrors();

    expect($campaign->refresh()->message_template_id)->toBe($replacement->id)
        ->and($original->refresh()->usage_count)->toBe(0)
        ->and($replacement->refresh()->usage_count)->toBe(1);
});

it('refuses a template another tenant owns', function (): void {
    $foreign = MessageTemplate::factory()
        ->forClient(Client::factory()->create())
        ->create(['name' => 'Foreign reminder']);

    $this->actingAs($this->user)
        ->post(route('campaigns.store'), ($this->payload)(['message_template_id' => $foreign->id]))
        ->assertSessionHasErrors('message_template_id');

    expect(Campaign::query()->count())->toBe(0);
});

it('refuses a template the library no longer offers', function (): void {
    $archived = MessageTemplate::factory()->create(['name' => 'Archived reminder']);
    $archived->update(['status' => TemplateStatus::Archived]);

    $this->actingAs($this->user)
        ->post(route('campaigns.store'), ($this->payload)(['message_template_id' => $archived->id]))
        ->assertSessionHasErrors('message_template_id');

    expect(Campaign::query()->count())->toBe(0);
});

it('refuses a template written for another channel', function (): void {
    $email = MessageTemplate::factory()->channel(Channel::Email)->create(['name' => 'Statement email']);

    $this->actingAs($this->user)
        ->post(route('campaigns.store'), ($this->payload)(['message_template_id' => $email->id]))
        ->assertSessionHasErrors('message_template_id');

    expect(Campaign::query()->count())->toBe(0);
});
