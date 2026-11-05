<?php

namespace App\Enums;

/**
 * A Ghanaian mobile money network a wallet can be charged on.
 *
 * The value is the provider code Paystack expects in `mobile_money.provider`
 * when a charge is started from our own form.
 */
enum MobileMoneyProvider: string
{
    case Mtn = 'mtn';
    case Telecel = 'vod';
    case AirtelTigo = 'atl';

    public function label(): string
    {
        return match ($this) {
            self::Mtn => 'MTN Mobile Money',
            self::Telecel => 'Telecel Cash',
            self::AirtelTigo => 'AirtelTigo Money',
        };
    }

    /**
     * The number ranges the network issues, used to preselect the provider.
     *
     * @return list<string>
     */
    public function prefixes(): array
    {
        return match ($this) {
            self::Mtn => ['024', '025', '054', '055', '059'],
            self::Telecel => ['020', '050'],
            self::AirtelTigo => ['026', '027', '056', '057'],
        };
    }

    /**
     * The network a local `0XXXXXXXXX` number belongs to, when the prefix is known.
     */
    public static function forNumber(string $localNumber): ?self
    {
        $prefix = mb_substr($localNumber, 0, 3);

        foreach (self::cases() as $provider) {
            if (in_array($prefix, $provider->prefixes(), true)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * The networks as select options for the payment form.
     *
     * @return list<array{value: string, label: string, prefixes: list<string>}>
     */
    public static function options(): array
    {
        return array_map(fn (self $provider): array => [
            'value' => $provider->value,
            'label' => $provider->label(),
            'prefixes' => $provider->prefixes(),
        ], self::cases());
    }
}
