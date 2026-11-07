<?php

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * Campaign email leaves through the `broadcast` mailer, and the relay it points
 * at accepts plaintext submissions while presenting a certificate no client can
 * verify. Symfony upgrades to STARTTLS whenever the EHLO reply advertises it, so
 * an empty scheme alone still gets every message rejected with a certificate
 * failure: the `auto_tls` option is what keeps the relay plaintext.
 */
it('builds the broadcast mailer without the opportunistic TLS upgrade', function (): void {
    config()->set('mail.mailers.broadcast.host', 'relay.omnicast.test');
    config()->set('mail.mailers.broadcast.port', 2525);

    $transport = Mail::mailer('broadcast')->getSymfonyTransport();

    expect(config('mail.mailers.broadcast.transport'))->toBe('smtp')
        ->and($transport)->toBeInstanceOf(EsmtpTransport::class)
        ->and($transport->isAutoTls())->toBeFalse()
        ->and($transport->isTlsRequired())->toBeFalse();
});

it('starts the TLS upgrade again once auto_tls is switched on', function (): void {
    config()->set('mail.mailers.broadcast.host', 'relay.omnicast.test');
    config()->set('mail.mailers.broadcast.port', 465);
    config()->set('mail.mailers.broadcast.auto_tls', true);

    $transport = Mail::mailer('broadcast')->getSymfonyTransport();

    expect($transport->isAutoTls())->toBeTrue();
});
