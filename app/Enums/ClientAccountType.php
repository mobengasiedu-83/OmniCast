<?php

namespace App\Enums;

enum ClientAccountType: string
{
    case Business = 'business';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Business => 'Business account',
            self::Private => 'Private account',
        };
    }

    /**
     * Identification document types accepted for this account type.
     *
     * @return list<ClientDocumentType>
     */
    public function identificationTypes(): array
    {
        return match ($this) {
            self::Business => [
                ClientDocumentType::BusinessRegistration,
                ClientDocumentType::TaxCertificate,
                ClientDocumentType::AuthorizationLetter,
                ClientDocumentType::Other,
            ],
            self::Private => [
                ClientDocumentType::IdDocument,
                ClientDocumentType::AuthorizationLetter,
                ClientDocumentType::Other,
            ],
        };
    }
}
