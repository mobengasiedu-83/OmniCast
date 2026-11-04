<?php

use App\Enums\CampaignContactStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\SmsMessageEventType;
use App\Enums\SmsMessageStatus;
use App\Jobs\DispatchCampaignJob;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\SenderId;
use App\Models\SmsMessage;
use App\Services\CampaignDispatcher;
use App\Services\CampaignService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Queue::fake();
    config()->set('campaigns.require_approval', false);
    config()->set('sms.webhook.secret', 'receipt-secret');

    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 20.0, smsPrice: 0.05);

    $service = app(CampaignService::class);

    $list = ContactList::factory()->forClient($this->client)->create();
    $contact = Contact::factory()->forClient($this->client)->create(['phone' => '+233244000001']);
    $list->contacts()->attach($contact->id, ['added_at' => now()]);

    $sender = SenderId::factory()->for($this->client)->sms()->approved()->create();

    $campaign = $service->create([
        'client_id' => $this->client->id,
        'name' => 'Balance reminder',
        'channel' => Channel::Sms,
        'contact_list_id' => $list->id,
        'sender_id' => $sender->id,
        'sms_message' => 'Your statement is ready.',
    ], $this->user);

    $service->submit($campaign, $this->user);
    $service->launch($campaign->refresh());

    app(CampaignDispatcher::class)->run($campaign->refresh());

    $this->smsMessage = SmsMessage::query()->where('campaign_id', $campaign->id)->sole();
    $this->campaign = $campaign->refresh();
    // the recipient row the receipt moves
    $this->recipient = $this->smsMessage->campaignContact;

    /**
     * Post a signed receipt the way the network does.
     *
     * @param  array<string, mixed>  $payload
     */
    $this->postReceipt = function (array $payload, ?string $secret = 'receipt-secret'): TestResponse {
        $body = json_encode([...$payload]);

        return $this->call(
            'POST',
            route('webhooks.sms'),
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
        'reference' => $this->smsMessage->reference,
        'status' => 'DELIVRD',
        'message_id' => 'TC-991',
        'event_id' => 'receipt-1',
    ])
        ->assertStatus(202)
        ->assertJson(['status' => 'accepted']);

    $message = $this->smsMessage->refresh();
    $event = $message->events()->sole();

    expect($message->status)->toBe(SmsMessageStatus::Delivered)
        ->and($message->provider_status)->toBe('DELIVRD')
        ->and($message->provider_message_id)->toBe('TC-991')
        ->and($message->delivered_at)->not->toBeNull()
        ->and($event->type)->toBe(SmsMessageEventType::Delivered)
        ->and($event->provider_event_id)->toBe('receipt-1')
        ->and($this->recipient?->refresh()->status)->toBe(CampaignContactStatus::Delivered)
        ->and($this->recipient?->delivered_at)->not->toBeNull();
});

it('rejects a receipt without a valid signature', function (): void {
    ($this->postReceipt)(['reference' => $this->smsMessage->reference, 'status' => 'DELIVRD'], null)
        ->assertStatus(403);

    expect($this->smsMessage->refresh()->status)->toBe(SmsMessageStatus::Submitted);
});

it('rejects a delivery status it cannot map', function (): void {
    ($this->postReceipt)(['reference' => $this->smsMessage->reference, 'status' => 'TEAPOT'])
        ->assertStatus(422);

    expect($this->smsMessage->refresh()->status)->toBe(SmsMessageStatus::Submitted);
});

it('acknowledges a receipt for a reference it never submitted', function (): void {
    ($this->postReceipt)(['reference' => 'SM-UNKNOWN', 'status' => 'DELIVRD'])
        ->assertStatus(202)
        ->assertJson(['status' => 'ignored']);

    expect($this->smsMessage->refresh()->status)->toBe(SmsMessageStatus::Submitted);
});

it('ignores a receipt the network has already posted', function (): void {
    $payload = [
        'reference' => $this->smsMessage->reference,
        'status' => 'DELIVRD',
        'event_id' => 'receipt-1',
    ];

    ($this->postReceipt)($payload)->assertStatus(202);
    ($this->postReceipt)($payload)->assertStatus(202);

    expect($this->smsMessage->refresh()->events()->count())->toBe(1);
});

it('never un-delivers a message a later failure receipt reports', function (): void {
    ($this->postReceipt)([
        'reference' => $this->smsMessage->reference,
        'status' => 'DELIVRD',
        'event_id' => 'receipt-1',
    ])->assertStatus(202);

    ($this->postReceipt)([
        'reference' => $this->smsMessage->reference,
        'status' => 'UNDELIV',
        'event_id' => 'receipt-2',
    ])->assertStatus(202);

    expect($this->smsMessage->refresh()->status)->toBe(SmsMessageStatus::Delivered)
        ->and($this->recipient?->refresh()->status)->toBe(CampaignContactStatus::Delivered);
});
it('reopens the campaign and queues a resend when the network cannot deliver', function (): void {
    expect($this->campaign->status)->toBe(CampaignStatus::Completed);

    ($this->postReceipt)([
        'reference' => $this->smsMessage->reference,
        'status' => 'UNDELIV',
        'event_id' => 'receipt-3',
        'error' => 'The recipient handset is switched off.',
    ])->assertStatus(202);

    $message = $this->smsMessage->refresh();

    expect($message->status)->toBe(SmsMessageStatus::Undelivered)
        ->and($message->failed_at)->not->toBeNull()
        ->and($message->error_message)->toBe('The recipient handset is switched off.')
        ->and($this->recipient?->refresh()->status)->toBe(CampaignContactStatus::Queued)
        ->and($this->campaign->refresh()->status)->toBe(CampaignStatus::Running);

    Queue::assertPushed(DispatchCampaignJob::class, 2);
});

it('fails the recipient once the retry budget is spent', function (): void {
    $this->campaign->forceFill(['max_retries' => 1])->save();
    $this->smsMessage->campaignContact?->forceFill(['attempts' => 1])->save();

    ($this->postReceipt)([
        'reference' => $this->smsMessage->reference,
        'status' => 'EXPIRED',
        'event_id' => 'receipt-4',
    ])->assertStatus(202);

    expect($this->recipient?->refresh()->status)->toBe(CampaignContactStatus::Failed)
        ->and($this->recipient?->error_message)->toContain('after 1 attempts')
        ->and($this->campaign->refresh()->status)->toBe(CampaignStatus::Completed);

    Queue::assertPushed(DispatchCampaignJob::class, 1);
});

it('accepts a kannel receipt signed for its reference in the querystring', function (): void {
    $this->get(route('webhooks.sms', [
        'reference' => $this->smsMessage->reference,
        'sig' => hash_hmac('sha256', $this->smsMessage->reference, 'receipt-secret'),
        'status' => 'DELIVRD',
        'msgid' => 'kannel-7',
    ]))
        ->assertStatus(202)
        ->assertJson(['status' => 'accepted']);

    expect($this->smsMessage->refresh()->status)->toBe(SmsMessageStatus::Delivered)
        ->and($this->smsMessage->provider_message_id)->toBe('kannel-7');
});

it('rejects a querystring receipt whose signature belongs to another reference', function (): void {
    $this->get(route('webhooks.sms', [
        'reference' => $this->smsMessage->reference,
        'sig' => hash_hmac('sha256', 'SM-SOMETHING-ELSE', 'receipt-secret'),
        'status' => 'DELIVRD',
    ]))->assertStatus(403);

    expect($this->smsMessage->refresh()->status)->toBe(SmsMessageStatus::Submitted);
});
