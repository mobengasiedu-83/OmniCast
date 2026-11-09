<?php

namespace App\Enums;

/**
 * The activity a policy acknowledgement was collected from.
 *
 * The context is what makes an acceptance record defensible: it says not only
 * what a customer agreed to and when, but the screen they agreed on — an
 * application, a campaign submission or a wallet top-up.
 */
enum LegalAcceptanceContext: string
{
    case Registration = 'registration';
    case WalletTopUp = 'wallet-top-up';
    case CampaignSubmission = 'campaign-submission';
    case OrganisationSettings = 'organisation-settings';
    case Dpa = 'data-processing-agreement';

    public function label(): string
    {
        return match ($this) {
            self::Registration => 'Account application',
            self::WalletTopUp => 'Wallet top-up',
            self::CampaignSubmission => 'Campaign submission',
            self::OrganisationSettings => 'Organisation settings',
            self::Dpa => 'Data processing agreement',
        };
    }

    /**
     * The contexts, in the order the screens list them.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function catalogue(): array
    {
        return array_map(fn (self $context): array => [
            'value' => $context->value,
            'label' => $context->label(),
        ], self::cases());
    }

    /**
     * The context values, as stored on an acceptance.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $context): string => $context->value, self::cases());
    }
}
