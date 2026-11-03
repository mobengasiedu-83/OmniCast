<?php

use App\Enums\ClientDocumentStatus;
use App\Enums\ClientDocumentType;
use App\Enums\Role as RoleEnum;
use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    Storage::fake('local');

    $this->admin = platformUser();
    $this->client = Client::factory()->create();
});

it('stores an uploaded document for review', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.clients.documents.store', $this->client), [
            'type' => ClientDocumentType::BusinessRegistration->value,
            'title' => 'Certificate of incorporation',
            'document' => UploadedFile::fake()->create('registration.pdf', 240, 'application/pdf'),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $document = ClientDocument::query()->sole();

    expect($document->status)->toBe(ClientDocumentStatus::Pending)
        ->and($document->title)->toBe('Certificate of incorporation')
        ->and($document->original_name)->toBe('registration.pdf')
        ->and($document->uploaded_by)->toBe($this->admin->id)
        ->and($document->displaySize())->toEndWith('KB');

    Storage::disk('local')->assertExists($document->path);

    expect(Activity::query()->where('description', 'uploaded client document')->exists())->toBeTrue();
});

it('rejects an unsupported upload', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.clients.documents.store', $this->client), [
            'type' => ClientDocumentType::BusinessRegistration->value,
            'document' => UploadedFile::fake()->create('payload.zip', 12, 'application/zip'),
        ])
        ->assertSessionHasErrors('document');

    expect(ClientDocument::query()->count())->toBe(0);
});

it('verifies an uploaded document', function (): void {
    $document = ClientDocument::factory()->for($this->client)->create();

    $this->actingAs($this->admin)
        ->post(route('admin.clients.documents.review', [$this->client, $document]), [
            'decision' => 'verify',
        ])
        ->assertSessionHasNoErrors();

    $document->refresh();

    expect($document->status)->toBe(ClientDocumentStatus::Verified)
        ->and($document->isVerified())->toBeTrue()
        ->and($document->reviewed_by)->toBe($this->admin->id)
        ->and($document->reviewed_at)->not->toBeNull();
});

it('rejects a document with a reason', function (): void {
    $document = ClientDocument::factory()->for($this->client)->create();

    $this->actingAs($this->admin)
        ->post(route('admin.clients.documents.review', [$this->client, $document]), [
            'decision' => 'reject',
            'reason' => 'The scan is unreadable.',
        ])
        ->assertSessionHasNoErrors();

    $document->refresh();

    expect($document->status)->toBe(ClientDocumentStatus::Rejected)
        ->and($document->rejection_reason)->toBe('The scan is unreadable.');
});

it('requires a reason when rejecting a document', function (): void {
    $document = ClientDocument::factory()->for($this->client)->create();

    $this->actingAs($this->admin)
        ->post(route('admin.clients.documents.review', [$this->client, $document]), [
            'decision' => 'reject',
        ])
        ->assertSessionHasErrors('reason');

    expect($document->refresh()->status)->toBe(ClientDocumentStatus::Pending);
});

it('does not accept another tenant document through a guessed id', function (): void {
    $other = Client::factory()->create();
    $document = ClientDocument::factory()->for($other)->create();

    $this->actingAs($this->admin)
        ->post(route('admin.clients.documents.review', [$this->client, $document]), [
            'decision' => 'verify',
        ])
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->delete(route('admin.clients.documents.destroy', [$this->client, $document]))
        ->assertNotFound();

    expect($document->refresh()->status)->toBe(ClientDocumentStatus::Pending);
});

it('forbids platform users without the approve permission from reviewing', function (): void {
    $document = ClientDocument::factory()->for($this->client)->create();

    $this->actingAs(platformUser(RoleEnum::ClientOperator))
        ->post(route('admin.clients.documents.review', [$this->client, $document]), [
            'decision' => 'verify',
        ])
        ->assertForbidden();
});

it('removes a document and its file', function (): void {
    $document = ClientDocument::factory()->for($this->client)->create([
        'path' => 'client-documents/'.$this->client->id.'/contract.pdf',
    ]);

    Storage::disk('local')->put($document->path, 'signed');

    $this->actingAs($this->admin)
        ->delete(route('admin.clients.documents.destroy', [$this->client, $document]))
        ->assertSessionHasNoErrors();

    expect(ClientDocument::query()->count())->toBe(0);

    Storage::disk('local')->assertMissing($document->path);
});
