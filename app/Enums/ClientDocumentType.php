<?php

namespace App\Enums;

/**
 * The compliance documents a client tenant is asked to provide.
 */
enum ClientDocumentType: string
{
    case BusinessRegistration = 'business_registration';
    case TaxCertificate = 'tax_certificate';
    case IdDocument = 'id_document';
    case AuthorizationLetter = 'authorization_letter';
    case Contract = 'contract';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BusinessRegistration => 'Business registration',
            self::TaxCertificate => 'Tax certificate',
            self::IdDocument => 'Identity document',
            self::AuthorizationLetter => 'Authorization letter',
            self::Contract => 'Signed contract',
            self::Other => 'Other',
        };
    }

    /**
     * Whether onboarding cannot be signed off without this document.
     */
    public function isRequired(): bool
    {
        return in_array($this, [self::BusinessRegistration, self::TaxCertificate], true);
    }

    /**
     * The document types that must be verified before activation.
     *
     * @return list<self>
     */
    public static function required(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $type): bool => $type->isRequired(),
        ));
    }
}
