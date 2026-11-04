<?php

use App\Contracts\EmailGateway;
use App\Enums\EmailFormat;
use App\Enums\EmailMessageStatus;
use App\Mail\BroadcastEmail;
use App\Services\Email\OutboundEmail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    config()->set('email.broadcast.enabled', true);
    config()->set('email.webhook.secret', 'feedback-secret');
    config()->set('email.mailer', 'broadcast');
    config()->set('email.from.address', 'noreply@omnicast.gh');
    config()->set('email.from.name', 'OmniCast');
});

/**
 * Build the message the dispatcher hands the transport.
 */
function outboundEmail(array $overrides = []): OutboundEmail
{
    return new OutboundEmail(
        reference: $overrides['reference'] ?? 'EM-1',
        to: $overrides['to'] ?? 'ama@example.com',
        from: $overrides['from'] ?? 'statements@omnicast.gh',
        subject: $overrides['subject'] ?? 'Your statement',
        body: $overrides['body'] ?? '<p>Hello <strong>Ama</strong>, your statement is ready.</p>',
        format: $overrides['format'] ?? EmailFormat::Html,
        fromName: $overrides['fromName'] ?? 'OmniCast',
    );
}

it('submits through the broadcast mailer and reports its message id', function (): void {
    config()->set('email.driver', 'smtp');

    Mail::fake();

    $gateway = app(EmailGateway::class);
    $result = $gateway->send(outboundEmail());

    expect($gateway->name())->toBe('smtp')
        ->and($result->accepted)->toBeTrue()
        ->and($result->providerMessageId)->toBe('EM-1@omnicast.gh');

    Mail::assertSent(BroadcastEmail::class, function (BroadcastEmail $mail): bool {
        return $mail->hasTo('ama@example.com')
            && $mail->subjectLine === 'Your statement'
            && $mail->reference === 'EM-1'
            && str_contains((string) $mail->htmlBody, 'your statement is ready')
            && str_contains($mail->textBody, 'your statement is ready')
            && ! str_contains($mail->textBody, '<p>');
    });
});

it('renders the branded shell with the authored html body', function (): void {
    config()->set('email.driver', 'smtp');

    Mail::fake();

    app(EmailGateway::class)->send(outboundEmail());

    Mail::assertSent(BroadcastEmail::class, function (BroadcastEmail $mail): bool {
        $rendered = $mail->render();

        return str_contains($rendered, '<strong>Ama</strong>')
            && str_contains($rendered, 'campaign EM-1');
    });
});

it('reports a mailer that does not exist as a rejection', function (): void {
    config()->set('email.driver', 'smtp');
    config()->set('email.mailer', 'missing-mailer');

    $result = app(EmailGateway::class)->send(outboundEmail());

    expect($result->accepted)->toBeFalse()
        ->and($result->message)->toContain('The mail server rejected the message');
});

it('keeps a plain text broadcast exactly as authored', function (): void {
    config()->set('email.driver', 'smtp');

    Mail::fake();

    $message = outboundEmail([
        'body' => 'Hello Ama, your statement is ready.',
        'format' => EmailFormat::Text,
    ]);

    app(EmailGateway::class)->send($message);

    expect($message->htmlBody())->toBeNull()
        ->and($message->textBody())->toBe('Hello Ama, your statement is ready.');

    Mail::assertSent(BroadcastEmail::class, fn (BroadcastEmail $mail): bool => $mail->htmlBody === null);
});

it('posts the reference, both bodies and its callback url to a generic json gateway', function (): void {
    config()->set('email.driver', 'generic_json');
    config()->set('email.generic_json.endpoint', 'https://mailer.test/send');
    config()->set('email.generic_json.token', 'aggregator-token');

    Http::fake([
        'mailer.test/*' => Http::response(['success' => true, 'id' => 'GN-9']),
    ]);

    $result = app(EmailGateway::class)->send(outboundEmail());

    expect($result->accepted)->toBeTrue()
        ->and($result->providerMessageId)->toBe('GN-9');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://mailer.test/send'
        && $request['reference'] === 'EM-1'
        && $request['subject'] === 'Your statement'
        && $request['format'] === 'html'
        && str_contains((string) $request['html'], '<strong>Ama</strong>')
        && ! str_contains((string) $request['text'], '<p>')
        && str_contains((string) $request['callback_url'], 'reference=EM-1')
        && str_contains((string) $request['callback_url'], 'sig='.hash_hmac('sha256', 'EM-1', 'feedback-secret'))
        && $request->hasHeader('Authorization', 'Bearer aggregator-token'));
});

it('refuses a generic json envelope flagged in error', function (): void {
    config()->set('email.driver', 'generic_json');
    config()->set('email.generic_json.endpoint', 'https://mailer.test/send');

    Http::fake(['mailer.test/*' => Http::response(['success' => false])]);

    $result = app(EmailGateway::class)->send(outboundEmail());

    expect($result->accepted)->toBeFalse()
        ->and($result->message)->toBe('The email gateway rejected the message.');
});

it('refuses a live transport while email broadcasting is switched off', function (): void {
    config()->set('email.broadcast.enabled', false);
    config()->set('email.driver', 'smtp');

    Mail::fake();

    $gateway = app(EmailGateway::class);
    $result = $gateway->send(outboundEmail());

    expect($gateway->name())->toBe('disabled')
        ->and($result->accepted)->toBeFalse()
        ->and($result->message)->toContain('EMAIL_BROADCAST_ENABLED');

    Mail::assertNothingSent();
});

it('maps provider feedback tokens onto message states', function (): void {
    expect(EmailMessageStatus::fromProvider('delivered'))->toBe(EmailMessageStatus::Delivered)
        ->and(EmailMessageStatus::fromProvider('250'))->toBe(EmailMessageStatus::Submitted)
        ->and(EmailMessageStatus::fromProvider('Hard Bounce'))->toBe(EmailMessageStatus::Bounced)
        ->and(EmailMessageStatus::fromProvider('spam_complaint'))->toBe(EmailMessageStatus::Complained)
        ->and(EmailMessageStatus::fromProvider('blocked'))->toBe(EmailMessageStatus::Rejected)
        ->and(EmailMessageStatus::fromProvider('deferred'))->toBe(EmailMessageStatus::Queued)
        ->and(EmailMessageStatus::fromProvider('something-else'))->toBeNull();
});
