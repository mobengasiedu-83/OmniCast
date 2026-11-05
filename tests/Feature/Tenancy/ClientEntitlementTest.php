<?php

use App\Enums\Channel;
use App\Enums\ClientService;
use App\Models\Campaign;
use App\Models\ClientEntitlement;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\SenderId;
use App\Services\CampaignService;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

it('updates the entitlement grid of a tenant', function (): void {
    [$client] = clientWithWallet();

    $this->actingAs(platformUser())
        ->put(route('admin.clients.entitlements.update', $client), [
            'entitlements' => [
                [
                    'service' => ClientService::Sms->value,
                    'is_enabled' => true,
                    'monthly_quota' => 50000,
                    'daily_quota' => 2000,
                    'notes' => 'Bundled with the starter plan.',
                ],
                [
                    'service' => ClientService::Voice->value,
                    'is_enabled' => false,
                ],
                [
                    'service' => ClientService::Email->value,
                    'is_enabled' => true,
                ],
            ],
        ])
        ->assertSessionHasNoErrors();

    $sms = ClientEntitlement::query()
        ->where('client_id', $client->id)
        ->where('service', ClientService::Sms->value)
        ->sole();

    expect($sms->is_enabled)->toBeTrue()
        ->and($sms->monthly_quota)->toBe(50000)
        ->and($sms->daily_quota)->toBe(2000)
        ->and($sms->quotaLabel())->toBe('50,000 / month · 2,000 / day')
        ->and($client->refresh()->allowsService(ClientService::Voice))->toBeFalse()
        ->and($client->allowsService(ClientService::Email))->toBeTrue()
        ->and(Activity::query()->where('description', 'updated client entitlements')->exists())->toBeTrue();
});

it('blocks a campaign on a channel the tenant is not entitled to', function (): void {
    [$client, , $user] = clientWithWallet();

    ClientEntitlement::factory()->for($client)->disabled()->create([
        'service' => ClientService::Sms,
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.store'), [
            'name' => 'Blocked reminder',
            'channel' => 'sms',
            'sms_message' => 'Your order is ready for collection.',
            'schedule_type' => 'now',
        ])
        ->assertSessionHasErrors('channel');

    expect(Campaign::query()->count())->toBe(0);
});

it('allows a channel that has no configured entitlement', function (): void {
    [$client, , $user] = clientWithWallet();

    $sender = SenderId::factory()->for($client)->sms()->approved()->create();

    $this->actingAs($user)
        ->post(route('campaigns.store'), [
            'name' => 'Allowed reminder',
            'channel' => 'sms',
            'sender_id' => $sender->id,
            'sms_message' => 'Your order is ready for collection.',
            'schedule_type' => 'now',
        ])
        ->assertSessionHasNoErrors();

    expect(Campaign::query()->count())->toBe(1)
        ->and($client->refresh()->allowsChannel(Channel::Sms))->toBeTrue();
});

it('only lets the platform team change entitlements', function (): void {
    [$client, , $user] = clientWithWallet();

    $this->actingAs($user)
        ->put(route('admin.clients.entitlements.update', $client), [
            'entitlements' => [
                ['service' => ClientService::Sms->value, 'is_enabled' => false],
            ],
        ])
        ->assertForbidden();

    expect(ClientEntitlement::query()->where('client_id', $client->id)->count())->toBe(0);
});

it('rejects campaign submissions that exceed a configured recipient quota', function (): void {
    [$client, , $user] = clientWithWallet();
    ClientEntitlement::factory()->for($client)->create([
        'service' => ClientService::Sms,
        'is_enabled' => true,
        'daily_quota' => 5,
    ]);
    $list = ContactList::factory()->forClient($client)->create();
    $contacts = Contact::factory()->count(4)->forClient($client)->create();
    $list->contacts()->attach($contacts->pluck('id'), ['added_at' => now()]);
    $sender = SenderId::factory()->for($client)->sms()->approved()->create();
    $campaigns = app(CampaignService::class);
    $attributes = [
        'client_id' => $client->id,
        'name' => 'Quota check',
        'channel' => Channel::Sms,
        'contact_list_id' => $list->id,
        'sender_id' => $sender->id,
        'sms_message' => 'Quota controlled message',
        'schedule_type' => 'now',
    ];
    $first = $campaigns->create($attributes, $user);
    $campaigns->submit($first, $user);
    $second = $campaigns->create([...$attributes, 'name' => 'Over quota'], $user);

    expect(fn () => $campaigns->submit($second, $user))
        ->toThrow(ValidationException::class);

    expect($second->refresh()->status->value)->toBe('draft');
    expect($second->reservations()->exists())->toBeFalse();
});
