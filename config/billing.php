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
    'support_email' => env('BILLING_SUPPORT_EMAIL', 'billing@omnicast.app'),

    'limits' => [
        'min_top_up' => (float) env('BILLING_MIN_TOP_UP', 1),
        'max_top_up' => (float) env('BILLING_MAX_TOP_UP', 100000),
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
    | @var array<string, array{name: string, price: float, popular: bool, tagline: string, units: array<string, int>}>
    */
    'packages' => (static function (): array {
        $packages = [
            'starter' => [
                'name' => 'Starter',
                'price' => 25.0,
                'popular' => false,
                'tagline' => 'Test one channel with a small audience.',
                'units' => ['sms' => 1300, 'voice' => 380, 'email' => 2600],
            ],
            'growth' => [
                'name' => 'Growth',
                'price' => 51.25,
                'popular' => true,
                'tagline' => 'The volume most campaigns settle into.',
                'units' => ['sms' => 2700, 'voice' => 780, 'email' => 5200],
            ],
            'business' => [
                'name' => 'Business',
                'price' => 250.0,
                'popular' => false,
                'tagline' => 'For weekly national broadcasts.',
                'units' => ['sms' => 13000, 'voice' => 3800, 'email' => 26000],
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'price' => 500.0,
                'popular' => false,
                'tagline' => 'High volume across every channel.',
                'units' => ['sms' => 26000, 'voice' => 7700, 'email' => 52000],
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
