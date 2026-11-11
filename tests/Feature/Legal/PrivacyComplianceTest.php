<?php

use App\Enums\AutomationRunStatus;
use App\Enums\ContactSuppressionReason;
use App\Enums\OrganisationDpaStatus;
use App\Enums\PrivacyRequestAction;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacyRetentionCategory;
use App\Enums\PrivacySubjectType;
use App\Enums\ReportType;
use App\Models\AutomationRun;
use App\Models\AutomationRunStep;
use App\Models\AutomationWorkflow;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\Client;
use App\Models\Contact;
use App\Models\ContactSuppression;
use App\Models\EmailMessage;
use App\Models\OrganisationDpa;
use App\Models\OrganisationDpaEvidence;
use App\Models\PlatformSetting;
use App\Models\PrivacyRequest;
use App\Models\PrivacyRetentionPolicy;
use App\Models\ReportExport;
use App\Models\SmppAccount;
use App\Models\SmsMessage;
use App\Models\User;
use App\Models\VoiceCall;
use App\Models\Wallet;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function approvedPrivacyRequest(
    Client $client,
    User $requester,
    User $reviewer,
    PrivacyRequestAction $action,
    PrivacySubjectType $subjectType = PrivacySubjectType::Account,
    ?Contact $contact = null,
): PrivacyRequest {
    return PrivacyRequest::query()->create([
        'reference' => (string) Str::uuid(),
        'client_id' => $client->id,
        'contact_id' => $contact?->id,
        'subject_type' => $subjectType,
        'action' => $action,
        'status' => PrivacyRequestStatus::Approved,
        'requested_by' => $requester->id,
        'reviewed_by' => $reviewer->id,
        'reviewed_at' => now(),
    ]);
}

function approvePrivacyRetention(PrivacyRetentionCategory $category, int $days): void
{
    PrivacyRetentionPolicy::query()->create([
        'category' => $category,
        'retention_days' => $days,
        'approved_by' => platformUser()->id,
        'approved_at' => now(),
    ]);
}

it('rejects a contact request that crosses client boundaries', function (): void {
    $client = Client::factory()->create();
    $otherClient = Client::factory()->create();
    $contact = Contact::factory()->forClient($otherClient)->create();
    $admin = platformUser();

    $this->actingAs($admin)
        ->post(route('admin.privacy-requests.store'), [
            'client_id' => $client->id,
            'subject_type' => PrivacySubjectType::Contact->value,
            'contact_id' => $contact->id,
            'action' => PrivacyRequestAction::Access->value,
        ])
        ->assertInvalid([
            'contact_id' => 'Choose a contact that belongs to the selected client account.',
        ]);

    expect(PrivacyRequest::query()->count())->toBe(0);
});

it('requires a different platform administrator to review a privacy request', function (): void {
    $client = Client::factory()->create();
    $requester = platformUser();
    $reviewer = platformUser();

    $this->actingAs($requester)
        ->post(route('admin.privacy-requests.store'), [
            'client_id' => $client->id,
            'subject_type' => PrivacySubjectType::Account->value,
            'action' => PrivacyRequestAction::Access->value,
        ])
        ->assertRedirect();

    $privacyRequest = PrivacyRequest::query()->sole();

    $this->actingAs($requester)
        ->post(route('admin.privacy-requests.review', $privacyRequest), ['decision' => 'approve'])
        ->assertForbidden();

    $this->actingAs($reviewer)
        ->post(route('admin.privacy-requests.review', $privacyRequest), ['decision' => 'approve'])
        ->assertRedirect();

    expect($privacyRequest->fresh()->status)->toBe(PrivacyRequestStatus::Approved)
        ->and($privacyRequest->fresh()->reviewed_by)->toBe($reviewer->id);
});

it('streams an approved account export without authentication secrets', function (): void {
    $client = Client::factory()->create();
    $requester = platformUser();
    $reviewer = platformUser();
    $user = User::factory()->forClient($client)->create([
        'name' => 'Export Subject',
        'email' => 'subject@example.test',
    ]);
    $contact = Contact::factory()->forClient($client)->create([
        'first_name' => 'Recipient',
        'email' => 'recipient@example.test',
    ]);
    $privacyRequest = approvedPrivacyRequest(
        $client,
        $requester,
        $reviewer,
        PrivacyRequestAction::Access,
    );

    $response = $this->actingAs($reviewer)
        ->get(route('admin.privacy-requests.export', $privacyRequest))
        ->assertDownload('privacy-request-'.$privacyRequest->reference.'.json')
        ->assertHeader('cache-control', 'no-store, private')
        ->assertHeader('content-type', 'application/json; charset=utf-8');

    $export = json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR);

    expect($export['client']['id'])->toBe($client->id)
        ->and($export['users'][0]['email'])->toBe($user->email)
        ->and($export['contacts'][0]['email'])->toBe($contact->email)
        ->and($export['users'][0])->not->toHaveKey('password');
    expect($privacyRequest->fresh()->status)->toBe(PrivacyRequestStatus::Completed);
});

it('anonymises contact data and invalidates copied recipient records after confirmation', function (): void {
    Storage::fake('local');
    config()->set('reports.exports.disk', 'local');

    $client = Client::factory()->create();
    $requester = platformUser();
    $reviewer = platformUser();
    $contact = Contact::factory()->forClient($client)->create([
        'first_name' => 'Private',
        'last_name' => 'Recipient',
        'phone' => '+233200000001',
        'email' => 'private@example.test',
    ]);
    $campaign = Campaign::factory()->for($client)->create();
    $campaignContact = CampaignContact::query()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'recipient_snapshot' => [
            'name' => 'Private Recipient',
            'phone' => '+233200000001',
            'email' => 'private@example.test',
        ],
    ]);
    $sms = SmsMessage::factory()->forCampaign($campaign)->create([
        'campaign_contact_id' => $campaignContact->id,
        'to_number' => '+233200000001',
        'body' => 'Hello Private Recipient',
    ]);
    $email = EmailMessage::factory()->forCampaign($campaign)->create([
        'campaign_contact_id' => $campaignContact->id,
        'to_address' => 'private@example.test',
        'body' => 'Hello Private Recipient',
    ]);
    $voiceCall = VoiceCall::factory()->forCampaign($campaign)->create([
        'campaign_contact_id' => $campaignContact->id,
        'to_number' => '+233200000001',
    ]);
    $workflow = AutomationWorkflow::factory()->for($client)->create();
    $run = AutomationRun::factory()
        ->for($client)
        ->for($contact)
        ->for($workflow, 'workflow')
        ->create([
            'status' => AutomationRunStatus::Running,
            'context' => ['recipient_email' => 'private@example.test'],
            'detail' => 'Processing Private Recipient',
        ]);
    $runStep = AutomationRunStep::factory()->for($run, 'run')->create([
        'detail' => 'Sent to private@example.test',
        'payload' => ['email' => 'private@example.test'],
    ]);
    $suppression = ContactSuppression::query()->create([
        'client_id' => $client->id,
        'channel' => 'sms',
        'identifier' => $contact->phone,
        'reason' => ContactSuppressionReason::Dnd,
        'source' => 'privacy-test',
        'is_active' => true,
    ]);
    $export = ReportExport::factory()->for($client)->completed()->create([
        'report' => ReportType::Contacts,
        'file_path' => 'reports/private-recipients.csv',
    ]);
    Storage::disk('local')->put((string) $export->file_path, 'recipient,phone,email');

    $privacyRequest = approvedPrivacyRequest(
        $client,
        $requester,
        $reviewer,
        PrivacyRequestAction::Erasure,
        PrivacySubjectType::Contact,
        $contact,
    );

    $this->actingAs($reviewer)
        ->post(route('admin.privacy-requests.erase', $privacyRequest), ['confirmation' => 'wrong'])
        ->assertInvalid(['confirmation']);

    expect(Contact::withTrashed()->findOrFail($contact->id)->email)->toBe('private@example.test');

    $this->actingAs($reviewer)
        ->post(route('admin.privacy-requests.erase', $privacyRequest), [
            'confirmation' => $privacyRequest->reference,
        ])
        ->assertRedirect();

    $anonymisedContact = Contact::withTrashed()->findOrFail($contact->id);

    expect($anonymisedContact->email)->toBeNull()
        ->and($anonymisedContact->first_name)->toBeNull()
        ->and($anonymisedContact->dnd)->toBeTrue()
        ->and($campaignContact->fresh()->recipient_snapshot)->toBeNull()
        ->and($sms->fresh()->to_number)->toBe('ERASED')
        ->and($sms->fresh()->body)->toBe('[redacted]')
        ->and($email->fresh()->to_address)->toBe('redacted@privacy.invalid')
        ->and($email->fresh()->body)->toBe('[redacted]')
        ->and($voiceCall->fresh()->to_number)->toBe('ERASED')
        ->and($run->fresh()->status)->toBe(AutomationRunStatus::Cancelled)
        ->and($run->fresh()->contact_id)->toBeNull()
        ->and($run->fresh()->context)->toBeNull()
        ->and($runStep->fresh()->payload)->toBeNull()
        ->and($suppression->fresh()->identifier)->toBe('+233200000001')
        ->and($privacyRequest->fresh()->status)->toBe(PrivacyRequestStatus::Completed)
        ->and($export->fresh()->file_path)->toBeNull();

    Storage::disk('local')->assertMissing('reports/private-recipients.csv');
});

it('anonymises a client account and revokes member and integration credentials', function (): void {
    $client = Client::factory()->create([
        'email' => 'account@example.test',
        'phone' => '+233200000002',
        'settings' => ['overrides' => ['mail.from' => 'account@example.test']],
    ]);
    $wallet = Wallet::factory()->for($client)->create(['balance' => 80]);
    $user = User::factory()->forClient($client)->create([
        'name' => 'Account Member',
        'email' => 'member@example.test',
    ]);
    $token = $user->createToken('privacy-test');
    $endpoint = WebhookEndpoint::factory()->for($client)->create();
    $endpointSecret = $endpoint->secret;
    $smppAccount = SmppAccount::factory()->for($client)->create();
    $smppPasswordHash = $smppAccount->password_hash;
    $dpa = OrganisationDpa::query()->create([
        'client_id' => $client->id,
        'status' => OrganisationDpaStatus::Signed,
    ]);
    $requester = platformUser();
    $reviewer = platformUser();
    $privacyRequest = approvedPrivacyRequest(
        $client,
        $requester,
        $reviewer,
        PrivacyRequestAction::Erasure,
    );

    $this->actingAs($reviewer)
        ->post(route('admin.privacy-requests.erase', $privacyRequest), [
            'confirmation' => $privacyRequest->reference,
        ])
        ->assertRedirect();

    expect($client->fresh()->status->value)->toBe('archived')
        ->and($client->fresh()->email)->toBeNull()
        ->and($client->fresh()->settings)->toBe([])
        ->and($user->fresh()->is_active)->toBeFalse()
        ->and($user->fresh()->email)->toBe('erased-user-'.$user->id.'@privacy.invalid')
        ->and($user->tokens()->count())->toBe(0)
        ->and($endpoint->fresh()->is_active)->toBeFalse()
        ->and($endpoint->fresh()->secret)->not->toBe($endpointSecret)
        ->and($smppAccount->fresh()->is_active)->toBeFalse()
        ->and($smppAccount->fresh()->password_hash)->not->toBe($smppPasswordHash)
        ->and($wallet->fresh()->balance)->toBe('80.0000')
        ->and($dpa->fresh()->status)->toBe(OrganisationDpaStatus::Signed)
        ->and($token->accessToken->fresh())->toBeNull();
});

it('requires an approved retention value and provides a separate disable action', function (): void {
    $admin = platformUser();
    $category = PrivacyRetentionCategory::ReportExports;

    $this->actingAs($admin)
        ->put(route('admin.privacy-retention.update', $category->value), [
            'days' => '',
            'legal_approval' => false,
        ])
        ->assertInvalid(['days', 'legal_approval']);

    expect(PrivacyRetentionPolicy::query()->count())->toBe(0);

    $this->actingAs($admin)
        ->put(route('admin.privacy-retention.update', $category->value), [
            'days' => 90,
            'legal_approval' => true,
        ])
        ->assertRedirect();

    $policy = PrivacyRetentionPolicy::query()->sole();

    expect($policy->retention_days)->toBe(90)
        ->and($policy->approved_by)->toBe($admin->id)
        ->and($policy->approved_at)->not->toBeNull();

    $this->actingAs($admin)
        ->delete(route('admin.privacy-retention.destroy', $category->value))
        ->assertRedirect();

    expect($policy->fresh()->retention_days)->toBeNull()
        ->and($policy->fresh()->approved_at)->toBeNull();
});

it('does not prune report files before approval and measures retention from completion', function (): void {
    Storage::fake('local');
    config()->set('reports.exports.disk', 'local');

    $client = Client::factory()->create();
    $expired = ReportExport::factory()->for($client)->completed()->create([
        'report' => ReportType::Contacts,
        'file_path' => 'reports/expired.csv',
    ]);
    $stillFresh = ReportExport::factory()->for($client)->completed()->create([
        'report' => ReportType::Contacts,
        'file_path' => 'reports/recently-completed.csv',
    ]);
    $expired->forceFill([
        'created_at' => now()->subDays(120),
        'completed_at' => now()->subDays(120),
    ])->save();
    $stillFresh->forceFill([
        'created_at' => now()->subDays(120),
        'completed_at' => now(),
    ])->save();
    Storage::disk('local')->put((string) $expired->file_path, 'expired');
    Storage::disk('local')->put((string) $stillFresh->file_path, 'fresh');

    $this->artisan('reports:prune-exports')->assertSuccessful();
    Storage::disk('local')->assertExists('reports/expired.csv');

    approvePrivacyRetention(PrivacyRetentionCategory::ReportExports, 90);

    $this->artisan('reports:prune-exports')->assertSuccessful();

    Storage::disk('local')->assertMissing('reports/expired.csv');
    Storage::disk('local')->assertExists('reports/recently-completed.csv');
    expect($expired->fresh()->file_path)->toBeNull()
        ->and($stillFresh->fresh()->file_path)->toBe('reports/recently-completed.csv');
});

it('prunes webhook deliveries only after approved retention measured from final delivery', function (): void {
    $endpoint = WebhookEndpoint::factory()->create();
    $expired = WebhookDelivery::factory()->for($endpoint, 'endpoint')->delivered()->create([
        'delivered_at' => now()->subDays(120),
    ]);
    $recentlyDelivered = WebhookDelivery::factory()->for($endpoint, 'endpoint')->delivered()->create([
        'created_at' => now()->subDays(120),
        'delivered_at' => now(),
    ]);
    $pending = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
        'created_at' => now()->subDays(120),
    ]);

    $this->artisan('webhooks:prune-deliveries')->assertSuccessful();
    expect(WebhookDelivery::query()->whereKey($expired->id)->exists())->toBeTrue();

    approvePrivacyRetention(PrivacyRetentionCategory::WebhookDeliveries, 90);

    $this->artisan('webhooks:prune-deliveries')->assertSuccessful();

    expect(WebhookDelivery::query()->whereKey($expired->id)->exists())->toBeFalse()
        ->and(WebhookDelivery::query()->whereKey($recentlyDelivered->id)->exists())->toBeTrue()
        ->and(WebhookDelivery::query()->whereKey($pending->id)->exists())->toBeTrue();
});

it('stores and serves signed DPA evidence only through private authenticated storage', function (): void {
    Storage::fake('local');

    $client = Client::factory()->create();
    $dpa = OrganisationDpa::query()->create([
        'client_id' => $client->id,
        'status' => OrganisationDpaStatus::Signed,
    ]);
    $admin = platformUser();
    $fileContents = "%PDF-1.4\nOmniCast signed DPA\n%%EOF";

    $this->actingAs($admin)
        ->post(route('admin.legal-dpas.evidence.store', $client), [
            'document' => UploadedFile::fake()->createWithContent('signed.pdf', $fileContents),
        ])
        ->assertRedirect();

    $evidence = OrganisationDpaEvidence::query()->sole();

    expect($evidence->organisation_dpa_id)->toBe($dpa->id)
        ->and($evidence->sha256)->toBe(hash('sha256', $fileContents))
        ->and($evidence->mime_type)->toBe('application/pdf');
    Storage::disk('local')->assertExists($evidence->path);

    $this->actingAs($admin)
        ->get(route('admin.legal-dpas.evidence.download', [
            'client' => $client->id,
            'evidence' => $evidence->id,
        ]))
        ->assertDownload('signed.pdf')
        ->assertHeader('cache-control', 'no-store, private');
});

it('rejects DPA evidence storage configured as public', function (): void {
    config()->set('filesystems.disks.public.visibility', 'public');
    Storage::fake('public');

    $client = Client::factory()->create();
    OrganisationDpa::query()->create([
        'client_id' => $client->id,
        'status' => OrganisationDpaStatus::Signed,
    ]);
    PlatformSetting::query()->create([
        'group' => 'storage',
        'key' => 'client_documents_disk',
        'value' => 'public',
        'is_secret' => false,
    ]);

    $this->actingAs(platformUser())
        ->post(route('admin.legal-dpas.evidence.store', $client), [
            'document' => UploadedFile::fake()->createWithContent('signed.pdf', "%PDF-1.4\n%%EOF"),
        ])
        ->assertUnprocessable();

    expect(OrganisationDpaEvidence::query()->count())->toBe(0);
});
