<?php

use App\Models\Contact;
use App\Models\ContactSuppression;
use Illuminate\Http\UploadedFile;

it('imports contacts from a CSV that only carries MSISDNs', function (): void {
    [$client, , $user] = clientWithWallet();

    $csv = "phone\n+233240001111\n0240002222\nnot-a-number\n";
    $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

    $this->actingAs($user)->post(route('contacts.import'), ['file' => $file])
        ->assertSessionHasNoErrors();

    $contacts = Contact::query()->where('client_id', $client->id)->orderBy('phone')->get();

    expect($contacts->pluck('phone')->all())->toBe(['+233240001111', '+233240002222'])
        ->and($contacts->first()->first_name)->toBeNull()
        ->and($contacts->first()->full_name)->toBe('+233240001111');
});

it('imports the DND column from the upload template', function (): void {
    [$client, , $user] = clientWithWallet();

    $csv = "phone,dnd,first_name\n+233240001111,yes,Ama\n+233240002222,no,Kofi\n";
    $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

    $this->actingAs($user)->post(route('contacts.import'), ['file' => $file])
        ->assertSessionHasNoErrors();

    $blocked = Contact::query()->where('phone', '+233240001111')->sole();
    $reachable = Contact::query()->where('phone', '+233240002222')->sole();

    expect($blocked->dnd)->toBeTrue()
        ->and($blocked->dnd_at)->not->toBeNull()
        ->and($reachable->dnd)->toBeFalse()
        ->and(ContactSuppression::query()->where('identifier', '+233240001111')->where('reason', 'dnd')->exists())->toBeTrue();
});

it('downloads the contact upload template with the mandatory MSISDN column first', function (): void {
    [, , $user] = clientWithWallet();

    $response = $this->actingAs($user)->get(route('contacts.import.template'));

    $response->assertOk()->assertDownload('contact-upload-template.csv');

    $csv = $response->streamedContent();

    expect(strtok($csv, "\n"))->toBe('phone,first_name,last_name,email,company,account_number,customer_id,location,language,timezone,dnd,tags,custom_loyalty_tier');
});
