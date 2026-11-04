<?php

use App\Contracts\SmsGateway;
use App\Enums\SmsEncoding;
use App\Services\Sms\OutboundSms;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('sms.broadcast.enabled', true);
    config()->set('sms.webhook.secret', 'receipt-secret');
});

it('submits over txtconnect with its bearer token and reads the provider id', function (): void {
    config()->set('sms.driver', 'txtconnect');
    config()->set('sms.txtconnect.endpoint', 'https://sms.test/send');
    config()->set('sms.txtconnect.token', 'secret-token');

    Http::fake([
        'sms.test/*' => Http::response([
            'data' => ['status_code' => '000', 'in_error' => false, 'message_id' => 'TC-77'],
        ]),
    ]);

    $gateway = app(SmsGateway::class);

    $result = $gateway->send(new OutboundSms(
        reference: 'SM-1',
        to: '+233244000001',
        from: 'OmniCast',
        body: 'Hello',
        encoding: SmsEncoding::Unicode,
        segments: 2,
    ));

    expect($gateway->name())->toBe('txtconnect')
        ->and($result->accepted)->toBeTrue()
        ->and($result->providerMessageId)->toBe('TC-77');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://sms.test/send'
        && $request['to'] === '233244000001'
        && $request['from'] === 'OmniCast'
        && $request['unicode'] === '1'
        && $request->hasHeader('Authorization', 'Bearer secret-token'));
});

it('treats a txtconnect envelope flagged in error as a rejection', function (): void {
    config()->set('sms.driver', 'txtconnect');
    config()->set('sms.txtconnect.endpoint', 'https://sms.test/send');
    config()->set('sms.txtconnect.token', 'secret-token');

    Http::fake([
        'sms.test/*' => Http::response([
            'data' => ['status_code' => '101', 'in_error' => true],
        ]),
    ]);

    $result = app(SmsGateway::class)->send(new OutboundSms(
        reference: 'SM-2',
        to: '+233244000001',
        from: 'OmniCast',
        body: 'Hello',
    ));

    expect($result->accepted)->toBeFalse()
        ->and($result->message)->toBe('The TxtConnect gateway rejected the message.');
});

it('posts a signed delivery url to the kannel bearerbox and accepts its 0: reply', function (): void {
    config()->set('sms.driver', 'kannel');
    config()->set('sms.kannel.url', 'https://kannel.test/cgi-bin/sendsms');
    config()->set('sms.kannel.username', 'omnicast');
    config()->set('sms.kannel.password', 'kannel-secret');

    Http::fake(['kannel.test/*' => Http::response('0: Accepted for delivery')]);

    $result = app(SmsGateway::class)->send(new OutboundSms(
        reference: 'SM-3',
        to: '+233244000001',
        from: 'OmniCast',
        body: 'Hello',
    ));

    expect($result->accepted)->toBeTrue()
        ->and($result->providerMessageId)->toBeNull();

    Http::assertSent(function ($request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_starts_with($request->url(), 'https://kannel.test/cgi-bin/sendsms?')
            && $query['username'] === 'omnicast'
            && $query['to'] === '+233244000001'
            && $query['charset'] === 'GSM'
            && $query['dlr-mask'] === '31'
            && str_contains((string) $query['dlr-url'], 'reference=SM-3')
            && str_contains((string) $query['dlr-url'], 'sig='.hash_hmac('sha256', 'SM-3', 'receipt-secret'));
    });
});

it('refuses a kannel reply that is not an acceptance', function (): void {
    config()->set('sms.driver', 'kannel');
    config()->set('sms.kannel.url', 'https://kannel.test/cgi-bin/sendsms');

    Http::fake(['kannel.test/*' => Http::response('4: Authorization failed')]);

    $result = app(SmsGateway::class)->send(new OutboundSms(
        reference: 'SM-4',
        to: '+233244000001',
        from: 'OmniCast',
        body: 'Hello',
    ));

    expect($result->accepted)->toBeFalse()
        ->and($result->message)->toBe('The Kannel bearerbox rejected the message.');
});

it('posts the reference and callback url to a generic json gateway', function (): void {
    config()->set('sms.driver', 'generic_json');
    config()->set('sms.generic_json.endpoint', 'https://aggregator.test/sms');
    config()->set('sms.generic_json.token', 'aggregator-token');

    Http::fake([
        'aggregator.test/*' => Http::response(['success' => true, 'id' => 'GN-5']),
    ]);

    $result = app(SmsGateway::class)->send(new OutboundSms(
        reference: 'SM-5',
        to: '+233244000001',
        from: 'OmniCast',
        body: 'Hello',
    ));

    expect($result->accepted)->toBeTrue()
        ->and($result->providerMessageId)->toBe('GN-5');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://aggregator.test/sms'
        && $request['reference'] === 'SM-5'
        && $request['unicode'] === false
        && str_contains((string) $request['callback_url'], 'reference=SM-5'));
});

it('refuses a live provider while sms broadcasting is switched off', function (): void {
    config()->set('sms.broadcast.enabled', false);
    config()->set('sms.driver', 'kannel');
    config()->set('sms.kannel.url', 'https://kannel.test/cgi-bin/sendsms');

    $gateway = app(SmsGateway::class);

    $result = $gateway->send(new OutboundSms(
        reference: 'SM-6',
        to: '+233244000001',
        from: 'OmniCast',
        body: 'Hello',
    ));

    expect($gateway->name())->toBe('disabled')
        ->and($result->accepted)->toBeFalse()
        ->and($result->message)->toContain('SMS_BROADCAST_ENABLED');

    Http::assertNothingSent();
});
