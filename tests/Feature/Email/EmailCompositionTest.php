<?php

use App\Enums\Channel;
use App\Enums\EmailFormat;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\SenderId;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Queue::fake();
    Storage::fake('local');
    Storage::fake('public');

    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 20.0);

    $this->sender = SenderId::factory()->for($this->client)->email()->approved()->create();

    $list = ContactList::factory()->forClient($this->client)->create();
    $list->contacts()->attach(
        Contact::factory()->forClient($this->client)->create(['email' => 'ama@acme.test'])->id,
        ['added_at' => now()],
    );

    $this->payload = fn (array $attributes = []): array => [
        'name' => 'Statement broadcast',
        'channel' => Channel::Email->value,
        'contact_list_id' => $list->id,
        'sender_id' => $this->sender->id,
        'email_subject' => 'Your October statement',
        'email_body' => '<p>Your <strong>statement</strong> is ready.</p>',
        'email_format' => EmailFormat::Html->value,
        'schedule_type' => 'now',
        ...$attributes,
    ];
});

it('stores the files uploaded with an email campaign and lists them on the campaign page', function (): void {
    $this->actingAs($this->user)
        ->post(route('campaigns.store'), ($this->payload)([
            'email_attachments' => [
                UploadedFile::fake()->create('october-statement.pdf', 320, 'application/pdf'),
            ],
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $campaign = Campaign::query()->sole();
    $attachment = $campaign->emailAttachments()->sole();

    expect($attachment->original_name)->toBe('october-statement.pdf')
        ->and($attachment->storage_disk)->toBe('local')
        ->and($attachment->uploaded_by)->toBe($this->user->id);

    Storage::disk('local')->assertExists($attachment->path);

    $this->actingAs($this->user)
        ->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('campaign.email_format', EmailFormat::Html->value)
            ->has('campaign.email_attachments', 1)
            ->where('campaign.email_attachments.0.name', 'october-statement.pdf')
            ->where('campaign.email_attachments.0.is_image', false)
            ->where('campaign.email_attachments.0.download_url', route('campaigns.attachments.download', [
                'campaign' => $campaign->id,
                'attachment' => $attachment->id,
            ]))
        );
});

it('takes a stored attachment off the campaign only when the edit names its id', function (): void {
    $this->actingAs($this->user)
        ->post(route('campaigns.store'), ($this->payload)([
            'email_attachments' => [
                UploadedFile::fake()->create('statement.pdf', 120, 'application/pdf'),
            ],
        ]))
        ->assertSessionHasNoErrors();

    $campaign = Campaign::query()->sole();
    $attachment = $campaign->emailAttachments()->sole();

    $this->actingAs($this->user)
        ->put(route('campaigns.update', $campaign), ($this->payload)([
            'removed_attachment_ids' => [$attachment->id],
        ]))
        ->assertSessionHasNoErrors();

    expect($campaign->emailAttachments()->count())->toBe(0);

    Storage::disk('local')->assertMissing($attachment->path);
});

it('keeps a stored attachment when the edit does not mention it', function (): void {
    $this->actingAs($this->user)
        ->post(route('campaigns.store'), ($this->payload)([
            'email_attachments' => [
                UploadedFile::fake()->create('statement.pdf', 120, 'application/pdf'),
            ],
        ]))
        ->assertSessionHasNoErrors();

    $campaign = Campaign::query()->sole();

    $this->actingAs($this->user)
        ->put(route('campaigns.update', $campaign), ($this->payload)(['name' => 'Renamed broadcast']))
        ->assertSessionHasNoErrors();

    expect($campaign->emailAttachments()->count())->toBe(1);
});

it('refuses more attachments than one message may carry', function (): void {
    $files = [];

    foreach (range(1, 6) as $index) {
        $files[] = UploadedFile::fake()->create("page-{$index}.pdf", 10, 'application/pdf');
    }

    $this->actingAs($this->user)
        ->post(route('campaigns.store'), ($this->payload)(['email_attachments' => $files]))
        ->assertSessionHasErrors('email_attachments');

    expect(Campaign::query()->count())->toBe(0);
});

it('washes the markup before it stores an html body', function (): void {
    $this->actingAs($this->user)
        ->post(route('campaigns.store'), ($this->payload)([
            'email_body' => '<p onclick="steal()" style="color: #b42318">Hi <script>alert(1)</script><strong>there</strong></p>',
        ]))
        ->assertSessionHasNoErrors();

    $body = (string) Campaign::query()->sole()->email_body;

    expect($body)->toContain('<strong>there</strong>')
        ->toContain('color: #b42318')
        ->not->toContain('<script')
        ->not->toContain('onclick');
});

it('stores an inline image and hands its public url back to the editor', function (): void {
    $response = $this->actingAs($this->user)
        ->post(route('email.images.store'), [
            'image' => UploadedFile::fake()->image('chart.png', 120, 90),
        ])
        ->assertOk();

    $path = (string) $response->json('path');

    expect($path)->toStartWith('email-images/'.$this->client->id.'/')
        ->and((string) $response->json('url'))->not->toBe('');

    Storage::disk('public')->assertExists($path);
});

it('only lets a signed-in operator upload an inline image', function (): void {
    $this->post(route('email.images.store'), [
        'image' => UploadedFile::fake()->image('chart.png', 120, 90),
    ])->assertRedirect(route('login'));
});
