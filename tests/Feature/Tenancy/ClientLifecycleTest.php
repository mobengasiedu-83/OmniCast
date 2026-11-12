<?php

use App\Enums\ClientAccountType;
use App\Enums\ClientDocumentType;
use App\Enums\ClientStatus;
use App\Enums\Role as RoleEnum;
use App\Exceptions\InvalidClientTransition;
use App\Mail\ClientActivated;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Services\ClientLifecycle;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

it('activates a pending tenant once its documents are verified', function (): void {
    Mail::fake();

    $client = Client::factory()->pending()->create();
    $admin = platformUser(RoleEnum::FinanceOfficer);

    $this->actingAs($admin)
        ->post(route('admin.clients.activate', $client))
        ->assertSessionHas('toast');

    expect($client->refresh()->status)->toBe(ClientStatus::Pending);
    Mail::assertNothingOutgoing();

    ClientDocument::factory()->for($client)->verified()->create([
        'type' => ClientDocumentType::BusinessRegistration,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.clients.activate', $client))
        ->assertSessionHasNoErrors();

    $client->refresh();

    expect($client->status)->toBe(ClientStatus::Active)
        ->and($client->activated_at)->not->toBeNull()
        ->and($client->canBroadcast())->toBeTrue();

    Mail::assertQueuedOnce(ClientActivated::class);
    Mail::assertQueued(ClientActivated::class, fn (ClientActivated $mail): bool => $mail->hasTo($client->email));
});

it('activates a private account after one verified identity document', function (): void {
    $client = Client::factory()->pending()->create(['account_type' => ClientAccountType::Private]);

    ClientDocument::factory()->for($client)->verified()->create([
        'type' => ClientDocumentType::IdDocument,
    ]);

    $this->actingAs(platformUser(RoleEnum::FinanceOfficer))
        ->post(route('admin.clients.activate', $client))
        ->assertSessionHasNoErrors();

    expect($client->refresh()->status)->toBe(ClientStatus::Active);
});

it('refuses to move a tenant back to pending', function (): void {
    $client = Client::factory()->create();

    app(ClientLifecycle::class)->transition($client, ClientStatus::Pending);
})->throws(InvalidClientTransition::class);

it('suspends an active tenant with a reason and records the move', function (): void {
    $client = Client::factory()->create();
    $admin = platformUser(RoleEnum::FinanceOfficer);

    $this->actingAs($admin)
        ->post(route('admin.clients.suspend', $client), [
            'reason' => 'Unpaid invoices for two consecutive months.',
        ])
        ->assertSessionHasNoErrors();

    $client->refresh();

    expect($client->status)->toBe(ClientStatus::Suspended)
        ->and($client->status_reason)->toBe('Unpaid invoices for two consecutive months.')
        ->and($client->suspended_at)->not->toBeNull()
        ->and($client->canBroadcast())->toBeFalse();

    $entry = Activity::query()->where('description', 'changed client status')->latest('id')->first();

    expect($entry?->subject_id)->toBe($client->id)
        ->and($entry?->properties['from'] ?? null)->toBe(ClientStatus::Active->value)
        ->and($entry?->properties['to'] ?? null)->toBe(ClientStatus::Suspended->value)
        ->and($entry?->causer_id)->toBe($admin->id);
});

it('fails closed when a tenant status is missing', function (): void {
    $client = Client::factory()->make(['status' => null]);

    expect($client->canBroadcast())->toBeFalse();
});

it('requires a reason to suspend a tenant', function (): void {
    $client = Client::factory()->create();

    $this->actingAs(platformUser(RoleEnum::FinanceOfficer))
        ->post(route('admin.clients.suspend', $client), [])
        ->assertSessionHasErrors('reason');

    expect($client->refresh()->status)->toBe(ClientStatus::Active);
});

it('signs the users of a suspended tenant out', function (): void {
    [$client, , $user] = clientWithWallet();

    $client->forceFill(['status' => ClientStatus::Suspended, 'suspended_at' => now()])->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('signs the users of an archived tenant out', function (): void {
    [$client, , $user] = clientWithWallet();

    $client->forceFill(['status' => ClientStatus::Archived, 'archived_at' => now()])->save();

    $this->actingAs($user)
        ->get(route('wallet.index'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('treats archived as a terminal state', function (): void {
    $client = Client::factory()->archived()->create();
    $admin = platformUser(RoleEnum::FinanceOfficer);

    app(ClientLifecycle::class)->transition($client, ClientStatus::Active);
})->throws(InvalidClientTransition::class);

it('reports the blocked transition instead of failing the request', function (): void {
    $client = Client::factory()->archived()->create();

    $this->actingAs(platformUser(RoleEnum::FinanceOfficer))
        ->from(route('admin.clients.show', $client))
        ->post(route('admin.clients.reactivate', $client))
        ->assertRedirect(route('admin.clients.show', $client))
        ->assertSessionHas('toast');

    expect($client->refresh()->status)->toBe(ClientStatus::Archived);
});

it('brings a suspended tenant back into service', function (): void {
    $client = Client::factory()->suspended()->create();

    $this->actingAs(platformUser(RoleEnum::FinanceOfficer))
        ->post(route('admin.clients.reactivate', $client))
        ->assertSessionHasNoErrors();

    $client->refresh();

    expect($client->status)->toBe(ClientStatus::Active)
        ->and($client->suspended_at)->toBeNull();
});

it('only lets the platform team change a tenant lifecycle', function (): void {
    [$client, , $user] = clientWithWallet();

    $this->actingAs($user)
        ->post(route('admin.clients.suspend', $client), ['reason' => 'Testing the guard.'])
        ->assertForbidden();

    expect($client->refresh()->status)->toBe(ClientStatus::Active);
});
