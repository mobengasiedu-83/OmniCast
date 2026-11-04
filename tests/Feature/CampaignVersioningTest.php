<?php

use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\Role as RoleEnum;
use App\Exceptions\CampaignSnapshotLocked;
use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignVersion;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\SenderId;
use App\Models\User;
use App\Services\CampaignService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Queue::fake();
    config()->set('campaigns.require_approval', true);

    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 10.0, smsPrice: 0.05);

    $this->service = app(CampaignService::class);

    $this->list = ContactList::factory()->forClient($this->client)->create();
    $this->contacts = Contact::factory()->count(3)->forClient($this->client)->create();

    $this->list->contacts()->attach($this->contacts->pluck('id'), ['added_at' => now()]);

    $this->sender = SenderId::factory()->for($this->client)->sms()->approved()->create();

    $this->draft = fn (array $attributes = []): Campaign => $this->service->create([
        'client_id' => $this->client->id,
        'name' => 'Statement reminder',
        'channel' => Channel::Sms,
        'contact_list_id' => $this->list->id,
        'sender_id' => $this->sender->id,
        'sms_message' => 'Your statement is ready.',
        ...$attributes,
    ], $this->user);
});

it('freezes a version on submit and another on approval', function (): void {
    $campaign = ($this->draft)();

    $this->service->submit($campaign, $this->user);
    $this->service->approve($campaign, $this->user);

    $versions = $campaign->versions()->orderBy('version')->get();

    expect($versions)->toHaveCount(2)
        ->and($versions[0]->version)->toBe(1)
        ->and($versions[0]->status)->toBe(CampaignStatus::PendingApproval)
        ->and($versions[1]->version)->toBe(2)
        ->and($versions[1]->status)->toBe(CampaignStatus::Approved)
        ->and($versions[1]->frozen_at)->not->toBeNull()
        ->and($versions[1]->audience_size)->toBe(3)
        ->and($versions[1]->snapshot['sms_message'])->toBe('Your statement is ready.')
        ->and($versions[1]->fingerprint)->toHaveLength(64)
        ->and($campaign->refresh()->current_version)->toBe(2);
});

it('freezes a single approved version when the campaign approves itself', function (): void {
    config()->set('campaigns.require_approval', false);

    $campaign = ($this->draft)();

    $this->service->submit($campaign, $this->user);

    $versions = $campaign->versions()->orderBy('version')->get();

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Approved)
        ->and($versions)->toHaveCount(1)
        ->and($versions[0]->version)->toBe(1)
        ->and($versions[0]->status)->toBe(CampaignStatus::Approved)
        ->and($versions[0]->audience_size)->toBe(3)
        ->and($campaign->current_version)->toBe(1);

    $this->service->launch($campaign->refresh());

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Running);

    Queue::assertPushed(DispatchCampaignJob::class);
});

it('refuses to change or delete a frozen version', function (): void {
    $campaign = ($this->draft)();
    $version = CampaignVersion::factory()->for($campaign)->create(['version' => 1]);

    expect(fn () => $version->update(['fingerprint' => str_repeat('a', 64)]))
        ->toThrow(CampaignSnapshotLocked::class);

    expect(fn () => $version->delete())
        ->toThrow(CampaignSnapshotLocked::class);

    expect($version->refresh()->fingerprint)->not->toBe(str_repeat('a', 64));
});

it('blocks a launch when the campaign changed after approval', function (): void {
    $campaign = ($this->draft)();

    $this->service->submit($campaign, $this->user);
    $this->service->approve($campaign, $this->user);

    $campaign->forceFill(['sms_message' => 'Send this instead.'])->save();

    expect(fn () => $this->service->launch($campaign->refresh()))
        ->toThrow(ValidationException::class);

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Approved);

    Queue::assertNotPushed(DispatchCampaignJob::class);
});
it('duplicates a campaign into an editable draft', function (): void {
    $campaign = ($this->draft)();
    $this->service->submit($campaign, $this->user);

    $response = $this->actingAs($this->user)
        ->post(route('campaigns.duplicate', $campaign));

    $copy = Campaign::query()->where('duplicated_from_id', $campaign->id)->sole();

    $response->assertRedirect(route('campaigns.show', $copy));

    expect($copy->status)->toBe(CampaignStatus::Draft)
        ->and($copy->current_version)->toBe(0)
        ->and($copy->reference)->not->toBe($campaign->reference)
        ->and($copy->name)->toBe('Statement reminder (copy)')
        ->and($copy->sms_message)->toBe('Your statement is ready.')
        ->and($copy->contact_list_id)->toBe($this->list->id)
        ->and($copy->idempotency_key)->not->toBe($campaign->idempotency_key)
        ->and($copy->creator?->id)->toBe($this->user->id)
        ->and($copy->submitted_at)->toBeNull()
        ->and((float) $copy->estimated_cost)->toBe(0.15)
        ->and($copy->versions()->count())->toBe(0);
});

it('does not let a role without campaign management duplicate a campaign', function (): void {
    $campaign = ($this->draft)();

    $finance = User::factory()->forClient($this->client)->create();
    $finance->syncRoles([RoleEnum::FinanceOfficer->value]);

    $this->actingAs($finance)
        ->post(route('campaigns.duplicate', $campaign))
        ->assertForbidden();

    expect(Campaign::query()->where('duplicated_from_id', $campaign->id)->count())->toBe(0);
});

it('reuses the draft when the same idempotency key is submitted twice', function (): void {
    $payload = [
        'name' => 'Idempotent reminder',
        'channel' => 'sms',
        'sms_message' => 'Pick up your parcel.',
        'schedule_type' => 'now',
        'idempotency_key' => 'form-7f3c1b',
    ];

    $this->actingAs($this->user)->post(route('campaigns.store'), $payload)->assertSessionHasNoErrors();

    $campaign = Campaign::query()->sole();

    $this->actingAs($this->user)
        ->post(route('campaigns.store'), $payload)
        ->assertRedirect(route('campaigns.show', $campaign));

    expect(Campaign::query()->count())->toBe(1)
        ->and($campaign->refresh()->idempotency_key)->toBe('form-7f3c1b')
        ->and($campaign->estimated_recipients)->toBe(3);
});

it('keeps the original idempotency key when a draft is edited', function (): void {
    $campaign = ($this->draft)(['idempotency_key' => 'create-key']);

    $this->actingAs($this->user)
        ->put(route('campaigns.update', $campaign), [
            'name' => 'Renamed reminder',
            'channel' => 'sms',
            'sms_message' => 'Updated copy.',
            'schedule_type' => 'now',
            'idempotency_key' => 'edit-key',
        ])
        ->assertSessionHasNoErrors();

    expect($campaign->refresh()->idempotency_key)->toBe('create-key')
        ->and($campaign->name)->toBe('Renamed reminder');
});

it('launches an approved campaign whose content is unchanged', function (): void {
    $campaign = ($this->draft)();

    $this->service->submit($campaign, $this->user);
    $this->service->approve($campaign, $this->user);
    $this->service->launch($campaign->refresh());

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Running);

    Queue::assertPushed(DispatchCampaignJob::class);
});

it('lists the frozen versions on the campaign detail page', function (): void {
    $campaign = ($this->draft)();
    $this->service->submit($campaign, $this->user);
    $this->service->approve($campaign, $this->user);

    $this->actingAs($this->user)
        ->get(route('campaigns.show', $campaign))
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->has('versions', 2)
            ->where('versions.0.version', 2)
            ->where('versions.0.status', CampaignStatus::Approved->value)
            ->where('versions.1.version', 1)
            ->where('abilities.duplicate', true),
        );
});
