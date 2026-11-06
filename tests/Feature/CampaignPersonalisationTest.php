<?php

use App\Enums\Channel;
use App\Enums\EmailFormat;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\EmailMessage;
use App\Models\Rate;
use App\Models\RateCard;
use App\Models\SenderId;
use App\Models\SmsMessage;
use App\Services\CampaignDispatcher;
use App\Services\CampaignService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    Mail::fake();
    config()->set('campaigns.require_approval', false);
    config()->set('email.driver', 'log');
    config()->set('email.broadcast.enabled', true);

    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 20.0, smsPrice: 0.05);

    $rateCard = RateCard::factory()->for($this->client)->create(['channel' => Channel::Email]);
    Rate::factory()->for($rateCard, 'rateCard')->create([
        'channel' => Channel::Email,
        'unit_price' => 0.02,
    ]);

    $this->service = app(CampaignService::class);

    $list = ContactList::factory()->forClient($this->client)->create();
    $smsSender = SenderId::factory()->for($this->client)->sms()->approved()->create(['name' => 'OmniCast']);

    $this->emailSender = SenderId::factory()->for($this->client)->email()->approved()->create([
        'name' => 'statements@acme.test',
    ]);

    $this->launch = function (array $attributes, Contact $contact) use ($list, $smsSender): Campaign {
        $list->contacts()->attach($contact->id, ['added_at' => now()]);

        $campaign = $this->service->create([
            'client_id' => $this->client->id,
            'name' => 'Personalised broadcast',
            'channel' => Channel::Sms,
            'contact_list_id' => $list->id,
            'sender_id' => $smsSender->id,
            'sms_message' => 'Hello {{first_name}}, your balance is due.',
            ...$attributes,
        ], $this->user);

        $this->service->submit($campaign, $this->user);
        $this->service->launch($campaign->refresh());

        app(CampaignDispatcher::class)->run($campaign->refresh());

        return $campaign->refresh();
    };
});

it('fills the sms placeholders from the recipient the message is sent to', function (): void {
    $contact = Contact::factory()->forClient($this->client)->create([
        'first_name' => 'Ama',
        'last_name' => 'Mensah',
        'company' => 'Adom Ventures',
    ]);

    $campaign = ($this->launch)([
        'sms_message' => 'Hello {{first_name}} {{last_name}}, your balance at {{company}} is due.',
    ], $contact);

    $message = SmsMessage::query()->where('campaign_id', $campaign->id)->firstOrFail();

    expect($message->body)->toBe('Hello Ama Mensah, your balance at Adom Ventures is due.');
});

it('drops a placeholder the recipient has no value for instead of sending the braces', function (): void {
    $contact = Contact::factory()->forClient($this->client)->create([
        'first_name' => null,
        'last_name' => null,
        'customer_id' => null,
    ]);

    $campaign = ($this->launch)([
        'sms_message' => 'Hello {{first_name}}, your balance is due. Ref {{customer_id}}.',
    ], $contact);

    $message = SmsMessage::query()->where('campaign_id', $campaign->id)->firstOrFail();

    expect($message->body)->toBe('Hello, your balance is due. Ref.')
        ->and($message->body)->not->toContain('{{');
});

it('removes a placeholder it does not recognise instead of delivering it literally', function (): void {
    $contact = Contact::factory()->forClient($this->client)->create(['first_name' => 'Ama']);

    $campaign = ($this->launch)([
        'sms_message' => 'Hi {{first_name}}, call {{hotline}} today.',
    ], $contact);

    $message = SmsMessage::query()->where('campaign_id', $campaign->id)->firstOrFail();

    expect($message->body)->toBe('Hi Ama, call today.')
        ->and($message->body)->not->toContain('{{');
});

it('addresses a contact by a field held in its own metadata', function (): void {
    $contact = Contact::factory()->forClient($this->client)->create([
        'first_name' => 'Ama',
        'custom_fields' => ['kyc_tier' => 'Gold'],
    ]);

    $campaign = ($this->launch)([
        'sms_message' => 'Hi {{first_name}}, your {{custom_fields.kyc_tier}} account is verified.',
    ], $contact);

    $message = SmsMessage::query()->where('campaign_id', $campaign->id)->firstOrFail();

    expect($message->body)->toBe('Hi Ama, your Gold account is verified.');
});

it('bills the segments the rendered copy needs rather than the template', function (): void {
    $contact = Contact::factory()->forClient($this->client)->create([
        'first_name' => str_repeat('A', 120),
    ]);

    $campaign = ($this->launch)([
        'sms_message' => '{{first_name}} '.str_repeat('b', 100),
    ], $contact);

    $message = SmsMessage::query()->where('campaign_id', $campaign->id)->firstOrFail();

    expect($message->segments)->toBe(2)
        ->and((float) $message->cost)->toBe(0.1)
        ->and((float) $campaign->actual_cost)->toBe(0.1);
});

it('personalises the email subject and body per recipient', function (): void {
    $contact = Contact::factory()->forClient($this->client)->create([
        'first_name' => 'Ama',
        'company' => 'Adom Ventures',
    ]);

    $campaign = ($this->launch)([
        'channel' => Channel::Email,
        'sender_id' => $this->emailSender->id,
        'email_subject' => '{{first_name}}, your statement',
        'email_body' => '<p>Hi {{first_name}}, the figures for {{company}} are ready.</p>',
        'email_format' => EmailFormat::Html,
    ], $contact);

    $message = EmailMessage::query()->where('campaign_id', $campaign->id)->firstOrFail();

    expect($message->subject)->toBe('Ama, your statement')
        ->and($message->body)->toBe('<p>Hi Ama, the figures for Adom Ventures are ready.</p>');
});
