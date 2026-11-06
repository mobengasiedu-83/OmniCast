<?php

/*
|--------------------------------------------------------------------------
| Wallet funding and billing
|--------------------------------------------------------------------------
|
| A client funds its wallet through the hosted checkout of `driver`. Prices
| are quoted in the platform currency in major units (GHS 25.00); a gateway
| converts them to the subunit it expects (pesewas, kobo, cents) when called.
|
| The package catalogue is the "top up your wallet" grid: a client buys a
| bundle of pre-paid credit and the package price is credited to the wallet.
| The per-service allocation is the volume that price buys, shown to the
| client on the package card.
|
*/

return [

    'currency' => env('BILLING_CURRENCY', 'GHS'),

    /*
    | The funding gateway: `paystack`, or `log` to redirect straight back with
    | a simulated successful payment while a live key is not available.
    */
    'driver' => env('BILLING_DRIVER', 'paystack'),

    /*
    | The address the gateway is given when a tenant has stored none of its
    | own. It only ever receives the payment receipt.
    */
    'support_email' => env('BILLING_SUPPORT_EMAIL', 'billing@sysup360.com'),

    /*
    | The platform's own letterhead, printed on every invoice document a client
    | downloads. The name falls back to the application name and the e-mail to
    | the support address, so a valid invoice is issued out of the box; the
    | registered company details are supplied through the environment.
    */
    'issuer' => [
        'name' => env('BILLING_ISSUER_NAME') ?: env('APP_NAME', 'SysUp360 OmniCast'),
        'address' => env('BILLING_ISSUER_ADDRESS') ?: 'Accra, Ghana',
        'phone' => env('BILLING_ISSUER_PHONE') ?: null,
        'email' => env('BILLING_ISSUER_EMAIL') ?: env('BILLING_SUPPORT_EMAIL', 'billing@sysup360.com'),
        'website' => env('BILLING_ISSUER_WEBSITE') ?: null,
    ],

    /*
    | The published price list every channel quantity is derived from.
    |
    | A client buys credit, not a channel, so one amount has to be quotable in
    | SMS, voice minutes and emails at once. These are the selling rates: the
    | "choose your amount" slider divides the amount by each one to show what
    | the money buys, and a wallet package without an explicit allocation is
    | presented the same way.
    |
    | The units are the ones campaigns are billed in:
    |
    |   sms   — one unit is a part of up to 160 characters to one number, so a
    |           longer message costs one unit for each part it splits into.
    |   voice — one unit is a minute of the call to one number.
    |   email — one unit is one email to one address.
    |
    | Consumption is still priced from the rate card (see RateResolver); these
    | rates are what the platform publishes to buyers.
    */
    'rates' => [
        'sms' => (float) env('BILLING_RATE_SMS', 0.04),
        'voice' => (float) env('BILLING_RATE_VOICE', 0.30),
        'email' => (float) env('BILLING_RATE_EMAIL', 0.05),
    ],

    /*
    | Whether a client may name the amount they pay.
    |
    | With the slider on, the wallet and the price list offer "choose your own
    | amount" beside the packages: the client drags to the amount they want and
    | sees what it buys in SMS, voice minutes and emails. The platform team can
    | switch it off from Admin → Dynamic pricing, which stores
    | `billing.dynamic_pricing.enabled`; the wallet then sells packages only and
    | a submitted amount is refused at checkout.
    */
    'dynamic_pricing' => (bool) env('BILLING_DYNAMIC_PRICING', true),

    'limits' => [
        'min_top_up' => (float) env('BILLING_MIN_TOP_UP', 1),
        'max_top_up' => (float) env('BILLING_MAX_TOP_UP', 100000),

        /*
        | The range the "choose your amount" slider is drawn over. It sits
        | inside the top-up limits: the slider is a convenience, so it covers
        | the amounts most clients pay, while the typed amount field still
        | accepts anything between the limits above.
        */
        'slider' => [
            'min' => (float) env('BILLING_SLIDER_MIN', 10),
            'max' => (float) env('BILLING_SLIDER_MAX', 5000),
            'step' => (float) env('BILLING_SLIDER_STEP', 10),
            'default' => (float) env('BILLING_SLIDER_DEFAULT', 100),
        ],
    ],

    'paystack' => [
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),

        /*
        | Paystack signs a webhook with HMAC-SHA512 over the raw body using the
        | secret key of the integration. A dedicated value may be supplied when
        | the signing key differs from the API key.
        */
        'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),

        /*
        | The payment methods offered on the hosted page. Mobile money is the
        | default in Ghana; cards cover the corporate buyers.
        |
        | @var list<string>
        */
        'channels' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('PAYSTACK_CHANNELS', 'card,mobile_money,bank_transfer')),
        ))),

        'timeout' => (int) env('PAYSTACK_TIMEOUT', 20),
    ],

    /*
    | The top-up packages, keyed by the slug a client submits at checkout.
    | Override the whole catalogue with the BILLING_PACKAGES JSON variable.
    |
    | One price, your choice of channel: every package buys the listed volume of
    | SMS, voice minutes or emails for the same money, so the three allocations of
    | a package are worth each other. The allocation is derived from `rates`
    | above (price ÷ rate, rounded down) unless the package spells out its own
    | `units`, which only a deliberately sweetened bundle such as `sample` does.
    |
    | A package carries `enabled` for its default availability. The platform team
    | switches it on or off from Admin → Wallet packages, which stores a
    | `billing.<key>.enabled` platform setting that wins over the default: a
    | hidden package stays configured but leaves the price list and is refused at
    | checkout.
    |
    | @var array<string, array{name: string, price: float, popular: bool, tagline: string, units?: array<string, int>, enabled?: bool}>
    */
    'packages' => (static function (): array {
        $packages = [
            'starter' => [
                'name' => 'Launch',
                'price' => 51.25,
                'popular' => false,
                'tagline' => 'Start broadcasting with a small audience.',
            ],
            'growth' => [
                'name' => 'Boost',
                'price' => 243.75,
                'popular' => true,
                'tagline' => 'Grow your campaigns across channels.',
            ],
            'business' => [
                'name' => 'Scale',
                'price' => 2153.75,
                'popular' => false,
                'tagline' => 'Reach larger audiences with flexibility.',
            ],
            'enterprise' => [
                'name' => 'Max',
                'price' => 3990.0,
                'popular' => false,
                'tagline' => 'Built for high-volume campaigns.',
            ],

            /*
            | Ten pesewas to prove the live gateway end to end: the sample buys the
            | Launch allocation for almost nothing, so a real card or mobile money
            | charge can be settled and refunded without moving real money. It ships
            | hidden and is switched on from Admin → Wallet packages, so a
            | production catalogue never sells it by accident.
            */
            'sample' => [
                'name' => 'Sample',
                'price' => 0.10,
                'popular' => false,
                'tagline' => 'Ten-pesewa test top up for the live gateway.',
                'units' => ['sms' => 1281, 'voice' => 170, 'email' => 1025],
                'enabled' => false,
            ],
        ];

        $configured = env('BILLING_PACKAGES');

        if (! is_string($configured) || trim($configured) === '') {
            return $packages;
        }

        $decoded = json_decode($configured, true);

        return is_array($decoded) && $decoded !== [] ? $decoded : $packages;
    })(),

];
