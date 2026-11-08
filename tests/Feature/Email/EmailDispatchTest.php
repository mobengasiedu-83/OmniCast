<?php

use App\Contracts\EmailGateway;
use App\Enums\CampaignContactStatus;
use App\Enums\Channel;
use App\Enums\EmailFormat;
use App\Enums\EmailMessageStatus;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\EmailMessage;
use App\Models\Rate;
use App\Models\RateCard;
use App\Models\SenderId;
use App\Services\CampaignDispatcher;
use App\Services\CampaignService;
use App\Services\Email\EmailAttachmentStore;
use App\Services\Email\EmailDispatchResult;
use App\Services\Email\OutboundEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Queue::fake();
    Mail::fake();
    config()->set('campaigns.require_approval', false);
    config()->set('email.driver', 'log');
    config()->set('email.broadcast.enabled', true);

    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 20.0);

    $rateCard = RateCard::factory()->for($this->client)->create(['channel' => Channel::Email]);
    Rate::factory()->for($rateCard, 'rateCard')->create([
        'channel' => Channel::Email,
        'unit_price' => 0.02,
    ]);

    $this->service = app(CampaignService::class);

    $list = ContactList::factory()->forClient($this->client)->create();
    $contacts = Contact::factory()->count(2)->forClient($this->client)->create();
    $list->contacts()->attach($contacts->pluck('id'), ['added_at' => now()]);

    $sender = SenderId::factory()->for($this->client)->email()->approved()->create([
        'name' => 'statements@acme.test',
    ]);

    $this->emailCampaign = function (array $attributes = []) use ($list, $sender): Campaign {
        $campaign = $this->service->create([
            'client_id' => $this->client->id,
            'name' => 'Statement broadcast',
            'channel' => Channel::Email,
            'contact_list_id' => $list->id,
            'sender_id' => $sender->id,
            'email_subject' => 'Your October statement',
            'email_body' => '<p>Your statement is ready to view.</p>',
            'email_format' => EmailFormat::Html,
            ...$attributes,
        ], $this->user);

        $this->service->submit($campaign, $this->user);
        $this->service->launch($campaign->refresh());

        app(CampaignDispatcher::class)->run($campaign->refresh());

        return $campaign->refresh();
    };
});

it('logs an email per recipient with its subject, sender and cost', function (): void {
    $campaign = ($this->emailCampaign)();

    $messages = EmailMessage::query()->where('campaign_id', $campaign->id)->orderBy('id')->get();
    $message = $messages->first();

    expect($messages)->toHaveCount(2)
        ->and($message->provider)->toBe('log')
        ->and($message->from_address)->toBe('statements@acme.test')
        ->and($message->from_name)->toBe($this->client->name)
        ->and($message->subject)->toBe('Your October statement')
        ->and($message->format)->toBe(EmailFormat::Html)
        ->and($message->status)->toBe(EmailMessageStatus::Submitted)
        ->and($message->attempts)->toBe(1)
        ->and((float) $message->cost)->toBe(0.02)
        ->and($message->provider_message_id)->toBe('log-'.$message->reference)
        ->and($message->submitted_at)->not->toBeNull();

    expect($message->campaignContact?->status)->toBe(CampaignContactStatus::Delivered)
        ->and((float) $campaign->actual_cost)->toBe(0.04);

    Mail::assertNothingSent();
});

it('prices an email campaign per recipient', function (): void {
    $campaign = ($this->emailCampaign)();

    expect($campaign->estimated_recipients)->toBe(2)
        ->and((float) $campaign->estimated_cost)->toBe(0.04);
});

it('leaves a contact without an email address out of the audience', function (): void {
    $list = ContactList::factory()->forClient($this->client)->create();
    $reachable = Contact::factory()->forClient($this->client)->create(['email' => 'reachable@acme.test']);
    $unreachable = Contact::factory()->forClient($this->client)->create(['email' => null]);
    $list->contacts()->attach([$reachable->id, $unreachable->id], ['added_at' => now()]);

    $campaign = $this->service->create([
        'client_id' => $this->client->id,
        'name' => 'Statement broadcast',
        'channel' => Channel::Email,
        'contact_list_id' => $list->id,
        'sender_id' => SenderId::factory()->for($this->client)->email()->approved()->create()->id,
        'email_subject' => 'Your October statement',
        'email_body' => 'Your statement is ready to view.',
        'email_format' => EmailFormat::Text,
    ], $this->user);

    $this->service->submit($campaign, $this->user);
    $this->service->launch($campaign->refresh());
    app(CampaignDispatcher::class)->run($campaign->refresh());

    expect($campaign->refresh()->campaignContacts()->pluck('contact_id')->all())
        ->toBe([$reachable->id])
        ->and(EmailMessage::query()->where('campaign_id', $campaign->id)->count())->toBe(1);
});

it('hands the stored attachments to the transport with each message', function (): void {
    Storage::fake('local');

    $sent = new ArrayObject;

    $this->app->bind(EmailGateway::class, fn (): EmailGateway => new class($sent) implements EmailGateway
    {
        /**
         * @param  ArrayObject<int, OutboundEmail>  $sent
         */
        public function __construct(private ArrayObject $sent) {}

        public function send(OutboundEmail $email): EmailDispatchResult
        {
            $this->sent->append($email);

            return EmailDispatchResult::accepted('fake-'.$email->reference);
        }

        public function name(): string
        {
            return 'fake';
        }
    });

    $list = ContactList::factory()->forClient($this->client)->create();
    $list->contacts()->attach(
        Contact::factory()->forClient($this->client)->create(['email' => 'statement@acme.test']),
        ['added_at' => now()],
    );

    $campaign = $this->service->create([
        'client_id' => $this->client->id,
        'name' => 'Statement broadcast',
        'channel' => Channel::Email,
        'contact_list_id' => $list->id,
        'sender_id' => SenderId::factory()->for($this->client)->email()->approved()->create()->id,
        'email_subject' => 'Your October statement',
        'email_body' => '<p>Your statement is ready to view.</p>',
        'email_format' => EmailFormat::Html,
    ], $this->user);

    app(EmailAttachmentStore::class)->sync($campaign, [
        UploadedFile::fake()->create('statement.pdf', 120, 'application/pdf'),
    ], [], $this->user);

    $this->service->submit($campaign, $this->user);
    $this->service->launch($campaign->refresh());
    app(CampaignDispatcher::class)->run($campaign->refresh());

    expect(count($sent))->toBe(1);

    $email = $sent[0];

    expect($email->format)->toBe(EmailFormat::Html)
        ->and($email->body)->toContain('<p>Your statement is ready to view.</p>')
        ->and($email->attachments)->toHaveCount(1)
        ->and($email->attachments[0]->name)->toBe('statement.pdf')
        ->and($email->attachments[0]->mimeType)->toBe('application/pdf')
        ->and($email->attachments[0]->exists())->toBeTrue()
        ->and($email->deliverableAttachments())->toHaveCount(1);
});

it('records a refused submission and leaves the recipient queued for a retry', function (): void {
    $this->app->bind(EmailGateway::class, fn (): EmailGateway => new class implements EmailGateway
    {
        public function send(OutboundEmail $email): EmailDispatchResult
        {
            return EmailDispatchResult::rejected('The mail server rejected the message.');
        }

        public function name(): string
        {
            return 'fake';
        }
    });

    $campaign = ($this->emailCampaign)();

    $message = EmailMessage::query()->where('campaign_id', $campaign->id)->firstOrFail();

    expect($message->status)->toBe(EmailMessageStatus::Failed)
        ->and($message->submitted_at)->toBeNull()
        ->and($message->failed_at)->not->toBeNull()
        ->and($message->error_message)->toBe('The mail server rejected the message.')
        ->and($message->campaignContact?->status)->toBe(CampaignContactStatus::Queued)
        ->and($campaign->status->value)->toBe('running');
});

it('never contacts a live transport while email broadcasting is switched off', function (): void {
    config()->set('email.driver', 'smtp');
    config()->set('email.broadcast.enabled', false);

    $campaign = ($this->emailCampaign)();

    $message = EmailMessage::query()->where('campaign_id', $campaign->id)->firstOrFail();

    expect($message->provider)->toBe('disabled')
        ->and($message->status)->toBe(EmailMessageStatus::Failed)
        ->and($message->error_message)->toContain('EMAIL_BROADCAST_ENABLED');

    Mail::assertNothingSent();
});
