<?php

use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactSegment;
use App\Models\ContactSuppression;
use App\Models\Tag;
use App\Services\ContactEligibility;
use App\Services\ContactSegmentQuery;
use Illuminate\Support\Facades\URL;

it('stores a contact with only an MSISDN plus tags, custom fields and DND', function (): void {
    [$client, , $user] = clientWithWallet();
    $tag = Tag::factory()->for($client)->create(['name' => 'VIP']);

    $this->actingAs($user)->post(route('contacts.store'), [
        'phone' => '+233 24 000 1111',
        'email' => 'AMA@example.com',
        'dnd' => true,
        'custom_fields_json' => '{"tier":"gold"}',
        'tag_ids' => [$tag->id],
    ])->assertSessionHasNoErrors();

    $contact = Contact::query()->where('client_id', $client->id)->where('phone', '+233240001111')->sole();

    expect($contact->first_name)->toBeNull()
        ->and($contact->full_name)->toBe('+233240001111')
        ->and($contact->email)->toBe('ama@example.com')
        ->and($contact->dnd)->toBeTrue()
        ->and($contact->dnd_at)->not->toBeNull()
        ->and($contact->custom_fields)->toBe(['tier' => 'gold'])
        ->and($contact->tags()->whereKey($tag->id)->exists())->toBeTrue();

    $suppression = ContactSuppression::query()->sole();

    expect($suppression->channel)->toBe('all')
        ->and($suppression->reason->value)->toBe('dnd')
        ->and($suppression->identifier)->toBe('+233240001111')
        ->and($suppression->is_active)->toBeTrue();
});

it('rejects a contact without a phone number', function (): void {
    [, , $user] = clientWithWallet();

    $this->actingAs($user)->from(route('contacts.create'))->post(route('contacts.store'), [
        'first_name' => 'Ama',
        'email' => 'ama@example.com',
    ])->assertSessionHasErrors('phone');

    expect(Contact::query()->count())->toBe(0);
});

it('builds tenant-scoped saved segments from the supported filters', function (): void {
    [$client, , $user] = clientWithWallet();
    $matching = Contact::factory()->forClient($client)->create(['company' => 'Acme Ghana']);
    Contact::factory()->forClient($client)->create(['company' => 'Other']);

    $this->actingAs($user)->post(route('contacts.audiences.segments.store'), [
        'name' => 'Acme customers',
        'filters' => ['company' => 'Acme'],
    ])->assertSessionHasNoErrors();

    $segment = ContactSegment::query()->where('client_id', $client->id)->sole();

    expect(app(ContactSegmentQuery::class)->apply(Contact::query(), $segment->filters, $client->id)->pluck('id')->all())
        ->toBe([$matching->id]);
});

it('filters saved segments on the DND flag', function (): void {
    [$client] = clientWithWallet();
    $blocked = Contact::factory()->forClient($client)->dnd()->create();
    Contact::factory()->forClient($client)->create();

    $matched = app(ContactSegmentQuery::class)->apply(Contact::query(), ['dnd' => '1'], $client->id)->pluck('id')->all();

    expect($matched)->toBe([$blocked->id]);
});

it('blocks every phone channel for a client wide DND suppression', function (): void {
    $contact = Contact::factory()->create(['phone' => '+233240123456']);
    $admin = platformUser();

    $this->actingAs($admin)->post(route('contacts.audiences.suppressions.store'), [
        'channel' => 'all',
        'identifier' => '0240123456',
        'reason' => 'dnd',
        'global' => true,
    ])->assertSessionHasNoErrors();

    $suppression = ContactSuppression::query()->sole();
    $eligibility = app(ContactEligibility::class);

    expect($suppression->client_id)->toBeNull()
        ->and($eligibility->forChannel($contact, 'sms')['eligible'])->toBeFalse()
        ->and($eligibility->forChannel($contact, 'voice')['eligible'])->toBeFalse()
        ->and($eligibility->forChannel($contact, 'email')['eligible'])->toBeTrue()
        ->and($eligibility->forChannel($contact, 'sms')['reason'])->toBe('suppressed');

    $contact->update(['dnd' => true]);

    expect($eligibility->forChannel($contact->refresh(), 'email')['eligible'])->toBeFalse()
        ->and($eligibility->forChannel($contact, 'email')['reason'])->toBe('dnd');
});

it('reports missing addresses and duplicate email groups on the audience health screen', function (): void {
    [$client, , $user] = clientWithWallet();
    Contact::factory()->forClient($client)->count(2)->sequence(
        ['email' => 'same@example.test'],
        ['email' => 'SAME@example.test'],
    )->create();
    Contact::factory()->forClient($client)->create(['email' => null]);
    Contact::factory()->forClient($client)->dnd()->create(['email' => 'blocked@example.test']);

    $this->actingAs($user)->get(route('contacts.audiences.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('contacts/compliance')
            ->where('quality.total', 4)
            ->where('quality.dnd', 1)
            ->where('quality.missing_email', 1)
            ->where('quality.duplicate_emails', 1));
});

it('refuses global suppression management to client users', function (): void {
    [$client, , $user] = clientWithWallet();

    $this->actingAs($user)->post(route('contacts.audiences.suppressions.store'), [
        'channel' => 'sms',
        'identifier' => '+233240123456',
        'reason' => 'manual',
        'global' => true,
    ])->assertForbidden();

    expect(ContactSuppression::query()->count())->toBe(0)
        ->and($client->exists)->toBeTrue();
});

it('merges a duplicate contact into the survivor, keeping tags and DND', function (): void {
    [$client, , $user] = clientWithWallet();
    $tag = Tag::factory()->for($client)->create(['name' => 'VIP']);
    $survivor = Contact::factory()->forClient($client)->create(['email' => 'same@example.test', 'company' => null]);
    $duplicate = Contact::factory()->forClient($client)->dnd()->create(['email' => 'SAME@example.test', 'company' => 'Acme Ltd']);
    $duplicate->tags()->attach($tag);

    $this->actingAs($user)->post(route('contacts.audiences.duplicates.merge'), [
        'survivor_id' => $survivor->id,
        'duplicate_id' => $duplicate->id,
    ])->assertSessionHasNoErrors();

    expect($survivor->refresh()->company)->toBe('Acme Ltd')
        ->and((bool) $survivor->dnd)->toBeTrue()
        ->and($survivor->tags()->whereKey($tag->id)->exists())->toBeTrue()
        ->and(Contact::query()->whereKey($duplicate->id)->exists())->toBeFalse()
        ->and(Contact::withTrashed()->whereKey($duplicate->id)->exists())->toBeTrue();
});

it('updates a saved segment and its filters', function (): void {
    [$client, , $user] = clientWithWallet();
    $segment = ContactSegment::factory()->create(['client_id' => $client->id, 'name' => 'Old name', 'filters' => []]);

    $this->actingAs($user)->put(route('contacts.audiences.segments.update', $segment), [
        'name' => 'Accra retail',
        'description' => 'Retail buyers in Accra',
        'filters' => ['location' => 'Accra', 'has_email' => true],
    ])->assertSessionHasNoErrors();

    expect($segment->refresh()->name)->toBe('Accra retail')
        ->and($segment->filters)->toMatchArray(['location' => 'Accra', 'has_email' => true]);
});

it('adds a contact to the DND list from the signed stop-messages link', function (): void {
    [$client] = clientWithWallet();
    $contact = Contact::factory()->forClient($client)->create(['email' => 'ama@example.test']);

    $signedUrl = URL::temporarySignedRoute('contacts.dnd.request', now()->addDay(), ['contact' => $contact->id]);

    $this->get($signedUrl)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('contacts/dnd')
            ->where('contact.dnd', false)
            ->where('contact.email', 'a••@example.test')
            ->where('dndRequestUrl', $signedUrl));

    $this->post($signedUrl)->assertRedirect();

    expect($contact->refresh()->dnd)->toBeTrue()
        ->and($contact->dnd_at)->not->toBeNull()
        ->and(ContactSuppression::query()->where('identifier', $contact->phone)->where('reason', 'dnd')->exists())->toBeTrue();
});

it('rejects a tampered stop-messages link', function (): void {
    [$client] = clientWithWallet();
    $contact = Contact::factory()->forClient($client)->create();

    $this->get(route('contacts.dnd.request', $contact))->assertForbidden();

    expect($contact->refresh()->dnd)->toBeFalse();
});

it('applies bulk list membership and DND to the selected contacts', function (): void {
    [$client, , $user] = clientWithWallet();
    $list = ContactList::factory()->forClient($client)->create();
    $contacts = Contact::factory()->forClient($client)->count(2)->create();

    $this->actingAs($user)->post(route('contacts.bulk'), [
        'action' => 'add_to_list',
        'contact_ids' => $contacts->modelKeys(),
        'contact_list_id' => $list->id,
    ])->assertSessionHasNoErrors();

    expect($list->contacts()->count())->toBe(2);

    $this->actingAs($user)->post(route('contacts.bulk'), [
        'action' => 'mark_dnd',
        'contact_ids' => $contacts->modelKeys(),
    ])->assertSessionHasNoErrors();

    expect($contacts->first()->refresh()->dnd)->toBeTrue()
        ->and($contacts->last()->refresh()->dnd)->toBeTrue();

    $this->actingAs($user)->post(route('contacts.bulk'), [
        'action' => 'clear_dnd',
        'contact_ids' => $contacts->modelKeys(),
    ])->assertSessionHasNoErrors();

    expect($contacts->first()->refresh()->dnd)->toBeFalse()
        ->and(ContactSuppression::query()->where('channel', 'all')->where('is_active', true)->count())->toBe(0);
});

it('toggles DND from the contact detail quick action', function (): void {
    [$client, , $user] = clientWithWallet();
    $contact = Contact::factory()->forClient($client)->create();

    $this->actingAs($user)->post(route('contacts.dnd', $contact), ['dnd' => true])->assertSessionHasNoErrors();

    expect($contact->refresh()->dnd)->toBeTrue();

    $this->actingAs($user)->post(route('contacts.dnd', $contact), ['dnd' => false])->assertSessionHasNoErrors();

    expect($contact->refresh()->dnd)->toBeFalse()
        ->and($contact->fresh()->dnd_at)->toBeNull()
        ->and(ContactSuppression::query()->where('is_active', true)->count())->toBe(0);
});
