<?php

namespace App\Enums;

use Database\Seeders\LegalDocumentSeeder;

/**
 * The policy documents the platform publishes.
 *
 * The value of each case is the document's slug, which is also its public URL
 * segment (`/legal/{slug}`), so a route never has to map an identifier twice.
 * The five cases are seeded by {@see LegalDocumentSeeder};
 * an administrator may still add further documents in the same table.
 */
enum LegalDocumentType: string
{
    case Terms = 'terms';
    case Privacy = 'privacy';
    case AcceptableUse = 'acceptable-use';
    case RefundWallet = 'refund-wallet';
    case DataProcessingAgreement = 'data-processing-agreement';

    /**
     * The name the documents are referred to by.
     */
    public function label(): string
    {
        return match ($this) {
            self::Terms => 'Terms and Conditions',
            self::Privacy => 'Privacy Policy',
            self::AcceptableUse => 'Acceptable Use Policy',
            self::RefundWallet => 'Refund, Cancellation & Wallet Policy',
            self::DataProcessingAgreement => 'Data Processing Agreement',
        };
    }

    /**
     * A short form for tables, badges and footer links.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Terms => 'Terms',
            self::Privacy => 'Privacy',
            self::AcceptableUse => 'Acceptable use',
            self::RefundWallet => 'Refund & wallet',
            self::DataProcessingAgreement => 'Data processing',
        };
    }

    /**
     * The one-line description shown wherever the document is linked.
     */
    public function summary(): string
    {
        return match ($this) {
            self::Terms => 'The contract that governs registration, access to and use of the OmniCast platform.',
            self::Privacy => 'What personal data OmniCast processes, why, and the rights available to individuals.',
            self::AcceptableUse => 'The messaging standards every campaign, contact import and integration must meet.',
            self::RefundWallet => 'How the prepaid wallet, top-ups, failed payments, refunds and cancellations work.',
            self::DataProcessingAgreement => 'The controller-processor terms that apply when OmniCast sends on your behalf.',
        };
    }

    /**
     * Whether acceptance is collected when a client applies for an account.
     */
    public function isAcceptedAtRegistration(): bool
    {
        return in_array($this, [self::Terms, self::Privacy, self::AcceptableUse], true);
    }

    /**
     * Whether a campaign submission acknowledges this document.
     */
    public function isAcceptedOnCampaignSubmission(): bool
    {
        return $this === self::AcceptableUse;
    }

    /**
     * Whether funding the wallet acknowledges this document.
     */
    public function isAcceptedOnWalletTopUp(): bool
    {
        return $this === self::RefundWallet;
    }

    /**
     * The documents in the order the public legal page lists them.
     *
     * @return list<array{value: string, label: string, short_label: string, summary: string}>
     */
    public static function catalogue(): array
    {
        return array_map(fn (self $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
            'short_label' => $type->shortLabel(),
            'summary' => $type->summary(),
        ], self::cases());
    }

    /**
     * The document slugs, as stored on `legal_documents.slug`.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }
}
