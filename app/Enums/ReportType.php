<?php

namespace App\Enums;

/**
 * The reports that can be read on screen and generated as an export.
 *
 * The first six read a single tenant's traffic; the last four read across every
 * client and are therefore limited to the platform team.
 */
enum ReportType: string
{
    case Overview = 'overview';
    case Campaigns = 'campaigns';
    case Contacts = 'contacts';
    case Voice = 'voice';
    case Sms = 'sms';
    case Email = 'email';
    case Executive = 'executive';
    case Clients = 'clients';
    case Revenue = 'revenue';
    case Providers = 'providers';

    public function label(): string
    {
        return match ($this) {
            self::Overview => 'Overview',
            self::Campaigns => 'Campaigns',
            self::Contacts => 'Contacts',
            self::Voice => 'Voice',
            self::Sms => 'SMS',
            self::Email => 'Email',
            self::Executive => 'Executive',
            self::Clients => 'Clients',
            self::Revenue => 'Revenue',
            self::Providers => 'Provider performance',
        };
    }

    /**
     * Whether the report reads across every client.
     */
    public function isPlatformOnly(): bool
    {
        return in_array($this, [self::Executive, self::Clients, self::Revenue, self::Providers], true);
    }

    /**
     * The reports a client tenant may export.
     *
     * @return list<self>
     */
    public static function clientReports(): array
    {
        return [self::Overview, self::Campaigns, self::Contacts, self::Voice, self::Sms, self::Email];
    }

    /**
     * The reports the platform team may export.
     *
     * @return list<self>
     */
    public static function platformReports(): array
    {
        return [self::Executive, self::Clients, self::Revenue, self::Providers];
    }

    /**
     * The file name stem used for a generated export.
     */
    public function fileStem(): string
    {
        return 'omnicast-'.$this->value;
    }
}
