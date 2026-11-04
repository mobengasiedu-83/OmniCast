<?php

use App\Contracts\SmsGateway;
use App\Enums\CampaignContactStatus;
use App\Enums\Channel;
use App\Enums\SmsEncoding;
use App\Enums\SmsMessageStatus;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\SenderId;
use App\Models\SmsMessage;
use App\Services\CampaignDispatcher;
use App\Services\CampaignService;
use App\Services\Sms\OutboundSms;
use App\Services\Sms\SmsDispatchResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    config()->set('campaigns.require_approval', false);

    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 20.0, smsPrice: 0.05);

    $this->service = app(CampaignService::class);

    $list = ContactList::factory()->forClient($this->client)->create();
    $contacts = Contact::factory()->count(2)->forClient($this->client)->create();
    $list->contacts()->attach($contacts->pluck('id'), ['added_at' => now()]);

    $sender = SenderId::factory()->for($this->client)->sms()->approved()->create(['name' => 'OmniCast']);

    $this->smsCampaign = function (array $attributes = []) use ($list, $sender): Campaign {
        $campaign = $this->service->create([
            'client_id' => $this->client->id,
            'name' => 'Balance reminder',
            'channel' => Channel::Sms,
            'contact_list_id' => $list->id,
            'sender_id' => $sender->id,
            'sms_message' => str_repeat('a', 200),
            ...$attributes,
        ], $this->user);

        $this->service->submit($campaign, $this->user);
        $this->service->launch($campaign->refresh());

        app(CampaignDispatcher::class)->run($campaign->refresh());

        return $campaign->refresh();
    };
});

it('logs a message per recipient with its segments, sender and cost', function (): void {
    $campaign = ($this->smsCampaign)();

    $messages = SmsMessage::query()->where('campaign_id', $campaign->id)->orderBy('id')->get();
    $message = $messages->first();

    expect($messages)->toHaveCount(2)
        ->and($message->provider)->toBe('log')
        ->and($message->from_number)->toBe('OmniCast')
        ->and($message->status)->toBe(SmsMessageStatus::Submitted)
        ->and($message->encoding)->toBe(SmsEncoding::Gsm7)
        ->and($message->segments)->toBe(2)
        ->and($message->attempts)->toBe(1)
        ->and((float) $message->cost)->toBe(0.1)
        ->and($message->provider_message_id)->toBe('log-'.$message->reference)
        ->and($message->campaign_contact_id)->not->toBeNull()
        ->and($message->submitted_at)->not->toBeNull();

    expect(CampaignContactStatus::Delivered)
        ->toBe($message->campaignContact?->status)
        ->and((float) $campaign->actual_cost)->toBe(0.2);
});

it('records a unicode message at its wider segment size', function (): void {
    $campaign = ($this->smsCampaign)(['sms_message' => str_repeat('你', 80)]);

    $message = SmsMessage::query()->where('campaign_id', $campaign->id)->firstOrFail();

    expect($message->encoding)->toBe(SmsEncoding::Unicode)
        ->and($message->segments)->toBe(2)
        ->and($message->body)->toBe(str_repeat('你', 80));
});

it('records a refused submission and leaves the recipient queued for a retry', function (): void {
    $this->app->bind(SmsGateway::class, fn (): SmsGateway => new class implements SmsGateway
    {
        public function send(OutboundSms $message): SmsDispatchResult
        {
            return SmsDispatchResult::rejected('The gateway rejected the message.');
        }

        public function name(): string
        {
            return 'fake';
        }
    });

    $campaign = ($this->smsCampaign)();

    $message = SmsMessage::query()->where('campaign_id', $campaign->id)->firstOrFail();

    expect($message->status)->toBe(SmsMessageStatus::Failed)
        ->and($message->submitted_at)->toBeNull()
        ->and($message->failed_at)->not->toBeNull()
        ->and($message->error_message)->toBe('The gateway rejected the message.')
        ->and(CampaignContactStatus::Queued)
        ->toBe($message->campaignContact?->status)
        ->and($campaign->status->value)->toBe('running');
});

it('never contacts a live provider while sms broadcasting is switched off', function (): void {
    Http::fake();

    config()->set('sms.driver', 'txtconnect');
    config()->set('sms.broadcast.enabled', false);
    config()->set('sms.txtconnect.endpoint', 'https://sms.test/send');
    config()->set('sms.txtconnect.token', 'token');

    $campaign = ($this->smsCampaign)();

    $message = SmsMessage::query()->where('campaign_id', $campaign->id)->firstOrFail();

    expect($message->provider)->toBe('disabled')
        ->and($message->status)->toBe(SmsMessageStatus::Failed)
        ->and($message->error_message)->toContain('SMS_BROADCAST_ENABLED');

    Http::assertNothingSent();
});
