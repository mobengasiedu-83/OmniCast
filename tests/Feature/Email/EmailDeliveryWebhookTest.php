<?php

use App\Enums\CampaignContactStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\ContactSuppressionReason;
use App\Enums\EmailFormat;
use App\Enums\EmailMessageEventType;
use App\Enums\EmailMessageStatus;
use App\Jobs\DispatchCampaignJob;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactSuppression;
use App\Models\EmailMessage;
use App\Models\SenderId;
use App\Services\CampaignDispatcher;
use App\Services\CampaignService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Queue::fake();
    Mail::fake();
    config()->set('campaigns.require_approval', false);
    config()->set('email.driver', 'log');
    config()->set('email.broadcast.enabled', true);
    config()->set('email.webhook.secret', 'feedback-secret');

    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 20.0);

    $service = app(CampaignService::class);

    $list = ContactList::factory()->forClient($this->client)->create();
    $contact = Contact::factory()->forClient($this->client)->create(['email' => 'ama@example.com']);
    $list->contacts()->attach($contact->id, ['added_at' => now()]);

    $campaign = $service->create([
        'client_id' => $this->client->id,
        'name' => 'Statement broadcast',
        'channel' => Channel::Email,
        'contact_list_id' => $list->id,
        'sender_id' => SenderId::factory()->for($this->client)->email()->approved()->create()->id,
        'email_subject' => 'Your October statement',
        'email_body' => '<p>Your statement is ready.</p>',
        'email_format' => EmailFormat::Html,
    ], $this->user);

    $service->submit($campaign, $this->user);
    $service->launch($campaign->refresh());

    app(CampaignDispatcher::class)->run($campaign->refresh());

    $this->emailMessage = EmailMessage::query()->where('campaign_id', $campaign->id)->sole();
    $this->campaign = $campaign->refresh();
    $this->recipient = $this->emailMessage->campaignContact;

    /**
     * Post a signed receipt the way the mail server does.
     *
     * @param  array<string, mixed>  $payload
     */
    $this->postReceipt = function (array $payload, ?string $secret = 'feedback-secret'): TestResponse {
        $body = json_encode([...$payload]);

        return $this->call(
            'POST',
            route('webhooks.email'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_OMNICAST_SIGNATURE' => $secret === null ? '' : hash_hmac('sha256', $body, $secret),
            ],
            $body,
        );
    };
});

it('confirms the delivery on the message and the recipient', function (): void {
    ($this->postReceipt)([
        'reference' => $this->emailMessage->reference,
        'status' => 'delivered',
        'message_id' => 'SMTP-991',
        'event_id' => 'receipt-1',
    ])
        ->assertStatus(202)
        ->assertJson(['status' => 'accepted']);

    $message = $this->emailMessage->refresh();
    $event = $message->events()->sole();

    expect($message->status)->toBe(EmailMessageStatus::Delivered)
        ->and($message->provider_status)->toBe('delivered')
        ->and($message->provider_message_id)->toBe('SMTP-991')
        ->and($message->delivered_at)->not->toBeNull()
        ->and($event->type)->toBe(EmailMessageEventType::Delivered)
        ->and($event->provider_event_id)->toBe('receipt-1')
        ->and($this->recipient?->refresh()->status)->toBe(CampaignContactStatus::Delivered);

    expect(ContactSuppression::query()->count())->toBe(0);
});

it('ignores a receipt for a reference this platform never submitted', function (): void {
    ($this->postReceipt)(['reference' => 'EM-UNKNOWN', 'status' => 'delivered'])
        ->assertStatus(202)
        ->assertJson(['status' => 'ignored']);

    expect($this->emailMessage->refresh()->status)->toBe(EmailMessageStatus::Submitted);
});

it('de-duplicates a receipt the mail server retries', function (): void {
    $payload = [
        'reference' => $this->emailMessage->reference,
        'status' => 'delivered',
        'event_id' => 'receipt-1',
    ];

    ($this->postReceipt)($payload)->assertStatus(202);
    ($this->postReceipt)($payload)->assertStatus(202);

    expect($this->emailMessage->refresh()->events()->count())->toBe(1);
});

it('never un-delivers a message a later bounce reports', function (): void {
    ($this->postReceipt)([
        'reference' => $this->emailMessage->reference,
        'status' => 'delivered',
        'event_id' => 'receipt-1',
    ])->assertStatus(202);

    ($this->postReceipt)([
        'reference' => $this->emailMessage->reference,
        'status' => 'bounced',
        'event_id' => 'receipt-2',
    ])->assertStatus(202);

    expect($this->emailMessage->refresh()->status)->toBe(EmailMessageStatus::Delivered)
        ->and($this->recipient?->refresh()->status)->toBe(CampaignContactStatus::Delivered)
        ->and(ContactSuppression::query()->count())->toBe(0);
});

it('reopens the campaign, suppresses the address and queues a resend on a bounce', function (): void {
    expect($this->campaign->status)->toBe(CampaignStatus::Completed);

    ($this->postReceipt)([
        'reference' => $this->emailMessage->reference,
        'status' => 'bounced',
        'event_id' => 'receipt-3',
        'error' => 'The mailbox does not exist.',
    ])->assertStatus(202);

    $message = $this->emailMessage->refresh();
    $suppression = ContactSuppression::query()->sole();

    expect($message->status)->toBe(EmailMessageStatus::Bounced)
        ->and($message->bounced_at)->not->toBeNull()
        ->and($message->error_message)->toBe('The mailbox does not exist.')
        ->and($this->recipient?->refresh()->status)->toBe(CampaignContactStatus::Queued)
        ->and($this->campaign->refresh()->status)->toBe(CampaignStatus::Running);

    expect($suppression->channel)->toBe('email')
        ->and($suppression->identifier)->toBe('ama@example.com')
        ->and($suppression->reason)->toBe(ContactSuppressionReason::Bounce)
        ->and($suppression->client_id)->toBe($this->client->id);

    Queue::assertPushed(DispatchCampaignJob::class, 2);
});

it('suppresses a complaining recipient without resending the message', function (): void {
    ($this->postReceipt)([
        'reference' => $this->emailMessage->reference,
        'status' => 'complaint',
        'event_id' => 'receipt-4',
        'error' => 'The recipient marked this message as spam.',
    ])->assertStatus(202);

    $suppression = ContactSuppression::query()->sole();

    expect($this->emailMessage->refresh()->status)->toBe(EmailMessageStatus::Complained)
        ->and($this->recipient?->refresh()->status)->toBe(CampaignContactStatus::Delivered)
        ->and($suppression->reason)->toBe(ContactSuppressionReason::Complaint);

    Queue::assertPushed(DispatchCampaignJob::class, 1);
});

it('fails the recipient once the retry budget is spent', function (): void {
    $this->campaign->forceFill(['max_retries' => 1])->save();
    $this->emailMessage->campaignContact?->forceFill(['attempts' => 1])->save();

    ($this->postReceipt)([
        'reference' => $this->emailMessage->reference,
        'status' => 'bounced',
        'event_id' => 'receipt-5',
    ])->assertStatus(202);

    expect($this->recipient?->refresh()->status)->toBe(CampaignContactStatus::Failed)
        ->and($this->recipient?->error_message)->toContain('after 1 attempts')
        ->and($this->campaign->refresh()->status)->toBe(CampaignStatus::Completed);

    Queue::assertPushed(DispatchCampaignJob::class, 1);
});

it('refuses a receipt whose body signature is wrong', function (): void {
    ($this->postReceipt)([
        'reference' => $this->emailMessage->reference,
        'status' => 'delivered',
    ], 'wrong-secret')->assertStatus(403);

    expect($this->emailMessage->refresh()->status)->toBe(EmailMessageStatus::Submitted);
});

it('refuses every receipt when no webhook secret is configured', function (): void {
    config()->set('email.webhook.secret', '');

    ($this->postReceipt)([
        'reference' => $this->emailMessage->reference,
        'status' => 'delivered',
    ])->assertStatus(403);

    expect($this->emailMessage->refresh()->status)->toBe(EmailMessageStatus::Submitted);
});

it('rejects a feedback token OmniCast cannot map', function (): void {
    ($this->postReceipt)([
        'reference' => $this->emailMessage->reference,
        'status' => 'not-a-status',
    ])->assertStatus(422);

    expect($this->emailMessage->refresh()->status)->toBe(EmailMessageStatus::Submitted);
});

it('accepts a querystring receipt signed for its reference', function (): void {
    $this->get(route('webhooks.email', [
        'reference' => $this->emailMessage->reference,
        'sig' => hash_hmac('sha256', $this->emailMessage->reference, 'feedback-secret'),
        'status' => 'delivered',
        'msgid' => 'smtp-7',
    ]))
        ->assertStatus(202)
        ->assertJson(['status' => 'accepted']);

    expect($this->emailMessage->refresh()->status)->toBe(EmailMessageStatus::Delivered)
        ->and($this->emailMessage->provider_message_id)->toBe('smtp-7');
});

it('rejects a querystring receipt whose signature belongs to another reference', function (): void {
    $this->get(route('webhooks.email', [
        'reference' => $this->emailMessage->reference,
        'sig' => hash_hmac('sha256', 'EM-SOMETHING-ELSE', 'feedback-secret'),
        'status' => 'delivered',
    ]))->assertStatus(403);

    expect($this->emailMessage->refresh()->status)->toBe(EmailMessageStatus::Submitted);
});
