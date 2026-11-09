<?php

use App\Enums\SupportTicketType;
use App\Mail\SupportAcknowledgementMail;
use App\Mail\SupportTeamAlertMail;
use App\Models\SupportArticle;
use App\Models\SupportCategory;
use App\Models\SupportTeam;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Services\Support\SupportSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

/**
 * A contact category routed to a team, as an operator would configure one.
 */
function contactCategory(array $overrides = []): SupportCategory
{
    $team = SupportTeam::factory()->create([
        'name' => 'Support desk',
        'slug' => 'support-desk',
        'email' => 'desk@example.com',
    ]);

    return SupportCategory::factory()->forTeam($team)->create([
        'name' => 'Customer support',
        'slug' => 'customer-support',
        'audience' => 'both',
        ...$overrides,
    ]);
}

it('shows the contact page with the published categories', function (): void {
    contactCategory();
    SupportCategory::factory()->create(['is_active' => false, 'slug' => 'hidden']);

    $this->get(route('contact.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contact')
            ->has('categories', 1)
            ->where('categories.0.slug', 'customer-support'));
});

it('records an enquiry and acknowledges it', function (): void {
    Mail::fake();
    Storage::fake('local');

    $category = contactCategory();

    $this->post(route('contact.store'), [
        'name' => 'Ama Mensah',
        'email' => 'ama@example.com',
        'phone' => '+233201234567',
        'company' => 'Mensah Traders',
        'category' => $category->slug,
        'subject' => 'Bulk pricing',
        'message' => 'We send about 20,000 messages a month and would like a quote.',
        'consent' => '1',
        'attachments' => [UploadedFile::fake()->image('screen.png')],
    ])->assertRedirect(route('contact.show'));

    $ticket = SupportTicket::query()->sole();

    expect($ticket->type)->toBe(SupportTicketType::Enquiry)
        ->and($ticket->source)->toBe('public')
        ->and($ticket->reference)->toStartWith('ENQ-')
        ->and($ticket->support_category_id)->toBe($category->id)
        ->and($ticket->support_team_id)->toBe($category->support_team_id)
        ->and($ticket->consent_at)->not->toBeNull()
        ->and($ticket->messages()->count())->toBe(1)
        ->and($ticket->messages()->first()->body)->toContain('20,000');

    expect(SupportTicketAttachment::query()->count())->toBe(1);

    Mail::assertQueued(SupportAcknowledgementMail::class);
    Mail::assertQueued(
        SupportTeamAlertMail::class,
        fn (SupportTeamAlertMail $mail): bool => $mail->hasTo('desk@example.com'),
    );
});

it('refuses an enquiry without consent', function (): void {
    $category = contactCategory();

    $this->post(route('contact.store'), [
        'name' => 'Ama Mensah',
        'email' => 'ama@example.com',
        'category' => $category->slug,
        'subject' => 'Bulk pricing',
        'message' => 'We would like a quote for bulk messaging please.',
    ])->assertSessionHasErrors('consent');

    expect(SupportTicket::query()->count())->toBe(0);
});

it('refuses a category that is not offered on the contact form', function (): void {
    contactCategory();
    SupportCategory::factory()->ticketOnly()->create(['slug' => 'internal-only']);

    $this->post(route('contact.store'), [
        'name' => 'Ama Mensah',
        'email' => 'ama@example.com',
        'category' => 'internal-only',
        'subject' => 'Bulk pricing',
        'message' => 'We would like a quote for bulk messaging please.',
        'consent' => '1',
    ])->assertSessionHasErrors('category');

    expect(SupportTicket::query()->count())->toBe(0);
});

it('refuses an enquiry that does not pass the challenge', function (): void {
    config()->set('services.turnstile.enabled', true);
    config()->set('services.turnstile.site_key', '1x00000000000000000000AA');
    config()->set('services.turnstile.secret_key', '1x0000000000000000000000000000000AA');

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => false]),
    ]);

    $category = contactCategory();

    $this->post(route('contact.store'), [
        'name' => 'Ama Mensah',
        'email' => 'ama@example.com',
        'category' => $category->slug,
        'subject' => 'Bulk pricing',
        'message' => 'We would like a quote for bulk messaging please.',
        'consent' => '1',
        'cf-turnstile-response' => 'forged',
    ])->assertSessionHasErrors('cf-turnstile-response');

    expect(SupportTicket::query()->count())->toBe(0);
});

it('accepts a verified challenge token and records the enquiry', function (): void {
    config()->set('services.turnstile.enabled', true);
    config()->set('services.turnstile.site_key', '1x00000000000000000000AA');
    config()->set('services.turnstile.secret_key', '1x0000000000000000000000000000000AA');

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true]),
    ]);

    $category = contactCategory();

    $this->post(route('contact.store'), [
        'name' => 'Ama Mensah',
        'email' => 'ama@example.com',
        'category' => $category->slug,
        'subject' => 'Bulk pricing',
        'message' => 'We would like a quote for bulk messaging please.',
        'consent' => '1',
        'cf-turnstile-response' => 'token-from-the-widget',
    ])->assertRedirect(route('contact.show'));

    expect(SupportTicket::query()->count())->toBe(1);
});

it('publishes the contact details an operator confirmed and hides the rest', function (): void {
    app(SupportSettings::class)->update([
        'support_email' => 'desk@example.com',
        'phone' => '+233 20 123 4567',
    ], null);

    $this->get(route('contact.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('contact.support_email', 'desk@example.com')
            ->where('contact.phone', '+233 20 123 4567')
            ->where('contact.whatsapp', null));
});

it('serves only published help articles', function (): void {
    SupportArticle::factory()->published()->create(['title' => 'Launch a campaign']);
    SupportArticle::factory()->create(['title' => 'Still a draft']);

    $this->get(route('help.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('help/index')
            ->has('articles', 1)
            ->where('articles.0.title', 'Launch a campaign'));
});

it('renders a published article as html', function (): void {
    $article = SupportArticle::factory()->published()->create([
        'slug' => 'launch',
        'body' => '## Overview',
    ]);

    $this->get(route('help.show', $article->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('help/show')
            ->where('article.slug', 'launch')
            ->where('article.html', fn (string $html): bool => str_contains($html, '<h2>Overview</h2>')));
});

it('hides an unpublished article behind a 404', function (): void {
    $article = SupportArticle::factory()->create(['slug' => 'draft']);

    $this->get(route('help.show', $article->slug))->assertNotFound();
});

it('keeps the client support menu behind the login', function (): void {
    $this->get(route('support.index'))->assertRedirect(route('login'));
});
