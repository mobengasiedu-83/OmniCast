<?php

namespace App\Services;

use DateTimeZone;
use Illuminate\Support\Arr;
use Locale;

class CountryCatalog
{
    private const COUNTRY_CODES = 'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS XK YE YT ZA ZM ZW';

    /** @var list<array{code: string, name: string, timezones: list<string>}>|null */
    private ?array $countriesCache = null;

    /** @var list<array{code: string, name: string, timezones: list<string>}>|null */
    private ?array $supportedCache = null;

    public function __construct(private readonly PlatformConfiguration $configuration) {}

    /** @return list<array{code: string, name: string, timezones: list<string>}> */
    public function all(): array
    {
        if ($this->countriesCache !== null) {
            return $this->countriesCache;
        }
        $enabled = $this->configuration->resolve('countries', 'enabled');
        $enabledCodes = is_string($enabled) && trim($enabled) !== ''
            ? array_fill_keys(array_map('trim', explode(',', strtoupper($enabled))), true)
            : null;

        $countries = $this->supported();
        if ($enabledCodes !== null) {
            $countries = array_values(array_filter($countries, fn (array $country): bool => isset($enabledCodes[$country['code']])));
        }

        $this->countriesCache = $countries;

        return $this->countriesCache;
    }

    /** @return list<array{code: string, name: string, timezones: list<string>}> */
    public function supported(): array
    {
        if ($this->supportedCache !== null) {
            return $this->supportedCache;
        }

        $countries = [];
        foreach (explode(' ', self::COUNTRY_CODES) as $code) {
            $timezones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $code);
            $name = class_exists(Locale::class) ? Locale::getDisplayRegion('und_'.$code, 'en') : $code;
            if ($timezones === [] || ! is_string($name) || $name === '' || $name === $code) {
                continue;
            }

            $countries[$code] = ['code' => $code, 'name' => $name, 'timezones' => array_values($timezones)];
        }

        uasort($countries, fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

        $this->supportedCache = array_values($countries);

        return $this->supportedCache;
    }

    public function defaultCountry(string $timezone): string
    {
        $countries = $this->all();
        foreach ($countries as $country) {
            if (in_array($timezone, $country['timezones'], true)) {
                return $country['code'];
            }
        }

        foreach ($countries as $country) {
            if ($country['code'] === 'GH') {
                return 'GH';
            }
        }

        return $countries[0]['code'] ?? 'GH';
    }

    /** @return list<string> */
    public function timezones(string $countryCode): array
    {
        $country = Arr::first($this->all(), fn (array $item): bool => $item['code'] === strtoupper($countryCode));

        return $country['timezones'] ?? [];
    }
}
