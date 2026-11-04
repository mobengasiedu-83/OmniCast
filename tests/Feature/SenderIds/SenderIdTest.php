<?php

use App\Enums\Role as RoleEnum;
use App\Enums\SenderIdChannel;
use App\Enums\SenderIdStatus;
use App\Mail\SenderIdRequestedMail;
use App\Models\CallerId;
use App\Models\Client;
use App\Models\SenderId;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

it('lists only the sender IDs of the signed-in client', function (): void {
    [$client, , $user] = clientWithWallet();

    SenderId::factory()->for($client)->create(['name' => 'OMNICAST']);
    SenderId::factory()->for($client)->email()->approved()->create();
    SenderId::factory()->create();

    $this->actingAs($user)
        ->get(route('sender-ids.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sender-ids/index')
            ->where('senderIds.total', 2)
            ->where('stats.total', 2)
            ->where('stats.approved', 1)
            ->where('can.create', true)
            ->has('channels', count(SenderIdChannel::cases())));

    $this->actingAs($user)
        ->get(route('sender-ids.index', ['channel' => 'email']))
        ->assertInertia(fn (Assert $page) => $page->where('senderIds.total', 1));
});

it('submits a sender ID request and emails every platform reviewer', function (): void {
    Mail::fake();

    [$client, , $user] = clientWithWallet();
    $reviewer = platformUser(RoleEnum::ComplianceOfficer);
    $superAdmin = platformUser();

    $this->actingAs($user)
        ->post(route('sender-ids.store'), [
            'channel' => 'sms',
            'name' => 'OmniCast GH',
            'country' => 'gh',
            'purpose' => 'Transactional balance alerts',
        ])
        ->assertRedirect(route('sender-ids.index'))
        ->assertSessionHasNoErrors();

    $senderId = SenderId::query()->sole();

    expect($senderId->name)->toBe('OmniCast GH')
        ->and($senderId->channel)->toBe(SenderIdChannel::Sms)
        ->and($senderId->country)->toBe('GH')
        ->and($senderId->status)->toBe(SenderIdStatus::Pending)
        ->and($senderId->client_id)->toBe($client->id)
        ->and($senderId->created_by)->toBe($user->id)
        ->and($senderId->requested_at)->not->toBeNull();

    Mail::assertQueued(SenderIdRequestedMail::class, fn (SenderIdRequestedMail $mail): bool => $mail->hasTo($reviewer->email));
    Mail::assertQueued(SenderIdRequestedMail::class, fn (SenderIdRequestedMail $mail): bool => $mail->hasTo($superAdmin->email));
    Mail::assertNotQueued(SenderIdRequestedMail::class, fn (SenderIdRequestedMail $mail): bool => $mail->hasTo($user->email));
});

it('rejects identifiers that do not fit the channel', function (array $payload, string $field): void {
    [, , $user] = clientWithWallet();

    $this->actingAs($user)
        ->from(route('sender-ids.create'))
        ->post(route('sender-ids.store'), $payload)
        ->assertSessionHasErrors($field);

    expect(SenderId::query()->count())->toBe(0);
})->with([
    'sms longer than 11 characters' => [['channel' => 'sms', 'name' => 'OMNICASTGHANA', 'country' => 'GH', 'purpose' => 'Transactional alerts for customers'], 'name'],
    'sms where the spaces push it past 11 characters' => [['channel' => 'sms', 'name' => 'OmniCast GHA', 'country' => 'GH', 'purpose' => 'Transactional alerts for customers'], 'name'],
    'sms shorter than 3 characters' => [['channel' => 'sms', 'name' => 'AB', 'country' => 'GH', 'purpose' => 'Transactional alerts for customers'], 'name'],
    'sms without letters or digits' => [['channel' => 'sms', 'name' => '###', 'country' => 'GH', 'purpose' => 'Transactional alerts for customers'], 'name'],
    'email is not an address' => [['channel' => 'email', 'name' => 'not-an-address', 'purpose' => 'Monthly statements for customers'], 'name'],
    'voice is not dialable' => [['channel' => 'voice', 'name' => '12', 'country' => 'GH', 'purpose' => 'Voice reminders for customers'], 'name'],
    'sms without a country' => [['channel' => 'sms', 'name' => 'OMNICAST', 'country' => '', 'purpose' => 'Transactional alerts for customers'], 'country'],
    'unknown channel' => [['channel' => 'fax', 'name' => 'OMNICAST', 'country' => 'GH', 'purpose' => 'Transactional alerts for customers'], 'channel'],
    'purpose that says nothing' => [['channel' => 'sms', 'name' => 'OMNICAST', 'country' => 'GH', 'purpose' => 'Ads'], 'purpose'],
]);

it('normalises the identifier for the selected channel', function (): void {
    [$client, , $user] = clientWithWallet();

    $this->actingAs($user)->post(route('sender-ids.store'), [
        'channel' => 'email',
        'name' => 'Broadcast@Example.COM',
        'purpose' => 'Monthly statements for customers',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->post(route('sender-ids.store'), [
        'channel' => 'voice',
        'name' => '024 000 1111',
        'country' => 'GH',
        'purpose' => 'Voice reminders for customers',
    ])->assertSessionHasNoErrors();

    $email = SenderId::query()->where('channel', SenderIdChannel::Email)->sole();
    $voice = SenderId::query()->where('channel', SenderIdChannel::Voice)->sole();

    expect($email->name)->toBe('broadcast@example.com')
        ->and($email->country)->toBeNull()
        ->and($voice->name)->toBe('+233240001111')
        ->and($voice->client_id)->toBe($client->id);
});

it('keeps the case of an sms sender id so two brands can live side by side', function (): void {
    [, , $user] = clientWithWallet();

    $this->actingAs($user)->post(route('sender-ids.store'), [
        'channel' => 'sms',
        'name' => 'OmniCast',
        'country' => 'GH',
        'purpose' => 'Transactional alerts for customers',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->post(route('sender-ids.store'), [
        'channel' => 'sms',
        'name' => 'omnicast',
        'country' => 'GH',
        'purpose' => 'Promotional offers for customers',
    ])->assertSessionHasNoErrors();

    expect(SenderId::query()->orderBy('id')->pluck('name')->all())
        ->toBe(['OmniCast', 'omnicast']);
});

it('follows the configured sms sender id limit', function (): void {
    [, , $user] = clientWithWallet();

    config()->set('sender-ids.sms.max_length', 5);

    $this->actingAs($user)
        ->from(route('sender-ids.create'))
        ->post(route('sender-ids.store'), [
            'channel' => 'sms',
            'name' => 'OMNICA',
            'country' => 'GH',
            'purpose' => 'Transactional alerts for customers',
        ])
        ->assertSessionHasErrors('name');

    config()->set('sender-ids.sms.max_length', 11);
    config()->set('sender-ids.sms.allow_spaces', false);

    $this->actingAs($user)
        ->from(route('sender-ids.create'))
        ->post(route('sender-ids.store'), [
            'channel' => 'sms',
            'name' => 'OMNI CAST',
            'country' => 'GH',
            'purpose' => 'Transactional alerts for customers',
        ])
        ->assertSessionHasErrors('name');

    expect(SenderId::query()->count())->toBe(0);
});

it('follows the configured email sender id limit', function (): void {
    [, , $user] = clientWithWallet();

    config()->set('sender-ids.email.max_length', 20);

    $this->actingAs($user)
        ->from(route('sender-ids.create'))
        ->post(route('sender-ids.store'), [
            'channel' => 'email',
            'name' => 'billing.statements@example.com',
            'purpose' => 'Monthly statements for customers',
        ])
        ->assertSessionHasErrors('name');

    expect(SenderId::query()->count())->toBe(0);
});

it('allows the same identity across channels but not twice on one channel', function (): void {
    [, , $user] = clientWithWallet();

    $this->actingAs($user)->post(route('sender-ids.store'), [
        'channel' => 'sms',
        'name' => 'OMNICAST',
        'country' => 'GH',
        'purpose' => 'Transactional alerts for customers',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->post(route('sender-ids.store'), [
        'channel' => 'sms',
        'name' => 'OMNICAST',
        'country' => 'GH',
        'purpose' => 'Transactional alerts for customers',
    ])->assertSessionHasErrors('name');

    $this->actingAs($user)->post(route('sender-ids.store'), [
        'channel' => 'email',
        'name' => 'omnicast@example.com',
        'purpose' => 'Transactional email for customers',
    ])->assertSessionHasNoErrors();

    expect(SenderId::query()->count())->toBe(2);
});

it('lets the client correct a rejected request and re-notifies reviewers', function (): void {
    Mail::fake();

    [$client, , $user] = clientWithWallet();
    $reviewer = platformUser(RoleEnum::ComplianceOfficer);
    $senderId = SenderId::factory()->for($client)->rejected()->create([
        'name' => 'OLD',
        'channel' => SenderIdChannel::Sms,
        'approved_by' => $reviewer->id,
    ]);

    $this->actingAs($user)
        ->put(route('sender-ids.update', $senderId), [
            'channel' => 'sms',
            'name' => 'NEWBRAND',
            'country' => 'GH',
            'purpose' => 'Resubmitted with the registered brand name',
        ])
        ->assertRedirect(route('sender-ids.index'))
        ->assertSessionHasNoErrors();

    $senderId->refresh();

    expect($senderId->name)->toBe('NEWBRAND')
        ->and($senderId->status)->toBe(SenderIdStatus::Pending)
        ->and($senderId->rejection_reason)->toBeNull()
        ->and($senderId->approved_by)->toBeNull()
        ->and($senderId->approved_at)->toBeNull();

    Mail::assertQueued(SenderIdRequestedMail::class, fn (SenderIdRequestedMail $mail): bool => $mail->hasTo($reviewer->email));
});

it('locks approved sender IDs from edits and withdrawal', function (): void {
    [$client, , $user] = clientWithWallet();
    $senderId = SenderId::factory()->for($client)->approved()->create(['name' => 'OMNICAST']);

    $this->actingAs($user)->get(route('sender-ids.edit', $senderId))->assertForbidden();

    $this->actingAs($user)->put(route('sender-ids.update', $senderId), [
        'channel' => 'sms',
        'name' => 'OMNICAST2',
        'country' => 'GH',
        'purpose' => 'Attempting to change an approved sender',
    ])->assertForbidden();

    $this->actingAs($user)->delete(route('sender-ids.destroy', $senderId))->assertForbidden();

    expect($senderId->refresh()->name)->toBe('OMNICAST');
});

it('approves a request and provisions an approved voice caller ID', function (): void {
    [$client] = clientWithWallet();
    $officer = platformUser(RoleEnum::ComplianceOfficer);
    $senderId = SenderId::factory()->for($client)->voice()->create(['name' => '+233240001234']);

    $this->actingAs($officer)
        ->post(route('sender-ids.approve', $senderId))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $senderId->refresh();
    $callerId = CallerId::query()->where('client_id', $client->id)->sole();

    expect($senderId->status)->toBe(SenderIdStatus::Approved)
        ->and($senderId->approved_by)->toBe($officer->id)
        ->and($senderId->approved_at)->not->toBeNull()
        ->and($callerId->number)->toBe('+233240001234')
        ->and($callerId->status)->toBe(SenderIdStatus::Approved)
        ->and($callerId->approved_by)->toBe($officer->id);
});

it('records the reason when a reviewer rejects a request', function (): void {
    [$client, , $user] = clientWithWallet();
    $officer = platformUser(RoleEnum::ComplianceOfficer);
    $senderId = SenderId::factory()->for($client)->create(['name' => 'OMNICAST']);

    $this->actingAs($officer)
        ->post(route('sender-ids.reject', $senderId), ['reason' => 'Brand does not match the registered company'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $senderId->refresh();

    expect($senderId->status)->toBe(SenderIdStatus::Rejected)
        ->and($senderId->rejection_reason)->toBe('Brand does not match the registered company')
        ->and($senderId->approved_by)->toBe($officer->id);

    $pending = SenderId::factory()->for($client)->create(['name' => 'OMNICAST2']);

    $this->actingAs($officer)
        ->post(route('sender-ids.reject', $pending), ['reason' => 'nope'])
        ->assertSessionHasErrors('reason');

    expect($pending->refresh()->status)->toBe(SenderIdStatus::Pending);

    $this->actingAs($user)
        ->get(route('sender-ids.index', ['status' => 'rejected']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('senderIds.total', 1)
            ->where('senderIds.data.0.rejection_reason', 'Brand does not match the registered company'));
});

it('withdraws a request that has not been approved', function (): void {
    [$client, , $user] = clientWithWallet();
    $senderId = SenderId::factory()->for($client)->create(['name' => 'OMNICAST']);

    $this->actingAs($user)
        ->delete(route('sender-ids.destroy', $senderId))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(SenderId::query()->count())->toBe(0)
        ->and(SenderId::withTrashed()->whereKey($senderId->id)->exists())->toBeTrue();
});

it('forbids operators and other tenants from changing a request', function (): void {
    [$client] = clientWithWallet();
    $request = SenderId::factory()->for($client)->create(['name' => 'OMNICAST']);

    [, , $operator] = clientWithWallet(role: RoleEnum::ClientOperator);

    $this->actingAs($operator)->post(route('sender-ids.store'), [
        'channel' => 'sms',
        'name' => 'INTRUDER',
        'country' => 'GH',
        'purpose' => 'Attempting to create without permission',
    ])->assertForbidden();

    $this->actingAs($operator)->get(route('sender-ids.edit', $request))->assertForbidden();

    $otherClient = Client::factory()->create();
    $outsider = User::factory()->forClient($otherClient)->create();
    $outsider->syncRoles([RoleEnum::ClientAdmin->value]);

    $this->actingAs($outsider)->get(route('sender-ids.edit', $request))->assertForbidden();

    expect($request->refresh()->name)->toBe('OMNICAST');
});
