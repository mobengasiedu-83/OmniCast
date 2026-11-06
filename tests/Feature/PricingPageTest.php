<?php

use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the published price list to a visitor', function (): void {
    $this->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pricing')
            ->where('currency', 'GHS')
            ->has('packages', 4)
            ->where('packages', fn (Collection $packages): bool => $packages
                ->map(fn (array $package): array => [
                    $package['key'],
                    $package['price'],
                    ...array_column($package['units'], 'quantity'),
                ])
                ->all() === [
                    ['starter', 51.25, 1281, 170, 1025],
                    ['growth', 243.75, 6093, 812, 4875],
                    ['business', 2153.75, 53843, 7179, 43075],
                    ['enterprise', 3990, 99750, 13300, 79800],
                ])
            ->where('limits.min', 1)
            ->where('limits.max', 100000),
        );
});

it('names each package and marks the popular one', function (): void {
    $this->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('packages', fn (Collection $packages): bool => $packages
                ->map(fn (array $package): array => [
                    $package['name'],
                    $package['popular'],
                ])
                ->all() === [
                    ['Launch', false],
                    ['Boost', true],
                    ['Scale', false],
                    ['Max', false],
                ])
        );
});

it('lists the sms, voice and email allocation of every package', function (): void {
    $this->get(route('pricing'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('packages.0.units', fn (Collection $units): bool => $units
                ->map(fn (array $unit): string => $unit['label'])
                ->all() === ['SMS', 'Voice minutes', 'Emails'])
            ->where('packages.3.units.2.formatted', '79,800'),
        );
});
