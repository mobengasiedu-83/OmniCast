<?php

use App\Enums\Integration;
use App\Models\PlatformSetting;
use App\Services\IntegrationScripts;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

it('lists every integration with the code stored for it', function (): void {
    PlatformSetting::query()->create([
        'group' => IntegrationScripts::GROUP,
        'key' => Integration::GoogleTagManager->value.'.head',
        'value' => '<script>window.dataLayer = window.dataLayer || [];</script>',
    ]);

    PlatformSetting::query()->create([
        'group' => IntegrationScripts::GROUP,
        'key' => Integration::GoogleTagManager->value.'.enabled',
        'value' => '1',
    ]);

    $this->actingAs(platformUser())
        ->get(route('admin.integrations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/integrations/index')
            ->has('integrations', count(Integration::cases()))
            ->where('integrations.0.value', Integration::GoogleAnalytics->value)
            ->where('integrations.0.head', null)
            ->where('integrations.0.enabled', false)
            ->where('integrations.1.value', Integration::GoogleTagManager->value)
            ->where('integrations.1.head', '<script>window.dataLayer = window.dataLayer || [];</script>')
            ->where('integrations.1.enabled', true)
            ->where('integrations.1.sample_head', fn (string $sample): bool => str_contains($sample, 'googletagmanager.com/gtm.js'))
            ->where('integrations.7.value', Integration::Custom->value));
});

it('saves a pasted snippet and prints it on every page', function (): void {
    $admin = platformUser();
    $head = '<script async src="https://www.googletagmanager.com/gtm.js?id=GTM-MD5FJNKN"></script>';
    $body = '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-MD5FJNKN"></iframe></noscript>';

    $this->actingAs($admin)
        ->put(route('admin.integrations.update'), [
            'integrations' => [
                [
                    'value' => Integration::GoogleTagManager->value,
                    'enabled' => true,
                    'head' => $head,
                    'body' => $body,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('toast');

    $settings = PlatformSetting::query()
        ->where('group', IntegrationScripts::GROUP)
        ->get()
        ->keyBy('key');

    expect($settings['google_tag_manager.head']->value)->toBe($head)
        ->and($settings['google_tag_manager.body']->value)->toBe($body)
        ->and($settings['google_tag_manager.enabled']->value)->toBe('1')
        ->and($settings['google_tag_manager.head']->updated_by)->toBe($admin->id)
        ->and(Activity::query()->where('description', 'updated integration scripts')->exists())->toBeTrue();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee($head, false)
        ->assertSee($body, false);
});

it('keeps a switched-off snippet saved but off the page', function (): void {
    $snippet = '<script>window.ga4 = true;</script>';

    $this->actingAs(platformUser())
        ->put(route('admin.integrations.update'), [
            'integrations' => [
                [
                    'value' => Integration::GoogleAnalytics->value,
                    'enabled' => false,
                    'head' => $snippet,
                    'body' => null,
                ],
            ],
        ])
        ->assertRedirect();

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('window.ga4 = true;', false);

    expect(PlatformSetting::query()
        ->where('group', IntegrationScripts::GROUP)
        ->where('key', 'google_analytics.head')
        ->sole()
        ->value)->toBe($snippet);
});

it('prints nothing while no integration is switched on', function (): void {
    expect(app(IntegrationScripts::class)->snippets())
        ->toBe(['head' => [], 'body' => []]);

    $this->get(route('home'))->assertOk();
});

it('keeps tenant users out of the integrations screen', function (): void {
    [, , $user] = clientWithWallet();

    $this->actingAs($user)
        ->get(route('admin.integrations.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->put(route('admin.integrations.update'), [
            'integrations' => [
                [
                    'value' => Integration::Custom->value,
                    'enabled' => true,
                    'head' => '<script>alert("hi")</script>',
                ],
            ],
        ])
        ->assertForbidden();

    expect(PlatformSetting::query()->where('group', IntegrationScripts::GROUP)->count())->toBe(0);
});

it('rejects an integration the platform does not know', function (): void {
    $this->actingAs(platformUser())
        ->put(route('admin.integrations.update'), [
            'integrations' => [
                ['value' => 'fax_relay', 'enabled' => true],
            ],
        ])
        ->assertSessionHasErrors('integrations.0.value');

    expect(PlatformSetting::query()->where('group', IntegrationScripts::GROUP)->count())->toBe(0);
});
