<?php

namespace App\Services\Sms;

use App\Enums\SmsEncoding;

/**
 * Works out the encoding and billable part count of an SMS body.
 */
class SmsSegmenter
{
    /**
     * The GSM 03.38 default alphabet.
     */
    public const GSM7_BASIC = '@£$¥èéùìòÇ'."\n".'Øø'."\r".'ÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';

    /**
     * Characters outside the basic alphabet that still fit in GSM-7, billed as two septets.
     */
    public const GSM7_EXTENDED = '^{}\[~]|€';

    /**
     * Determine the encoding the given message is billed on.
     *
     * Characters from the GSM 03.38 extension table stay in GSM-7, they are simply
     * billed as two septets each.
     */
    public function encodingFor(string $message): SmsEncoding
    {
        $basic = $this->characters(self::GSM7_BASIC);
        $extended = $this->characters(self::GSM7_EXTENDED);

        foreach ($this->characters($message) as $character) {
            if (! in_array($character, $basic, true) && ! in_array($character, $extended, true)) {
                return SmsEncoding::Unicode;
            }
        }

        return SmsEncoding::Gsm7;
    }

    /**
     * Count the billable units (septets for GSM-7, characters for Unicode) of the message.
     */
    public function unitsFor(string $message): int
    {
        $encoding = $this->encodingFor($message);
        $characters = $this->characters($message);

        if ($encoding === SmsEncoding::Unicode) {
            return count($characters);
        }

        $extended = $this->characters(self::GSM7_EXTENDED);
        $units = 0;

        foreach ($characters as $character) {
            $units += in_array($character, $extended, true) ? 2 : 1;
        }

        return $units;
    }

    /**
     * Count the billable parts of the message.
     */
    public function segmentsFor(string $message): int
    {
        $units = $this->unitsFor($message);

        if ($units === 0) {
            return 1;
        }

        $encoding = $this->encodingFor($message);

        if ($units <= $encoding->singlePartLimit()) {
            return 1;
        }

        return (int) ceil($units / $encoding->multiPartLimit());
    }

    /**
     * The full segmentation breakdown of the message.
     *
     * @return array{encoding: SmsEncoding, units: int, segments: int, remaining: int}
     */
    public function analyze(string $message): array
    {
        $encoding = $this->encodingFor($message);
        $units = $this->unitsFor($message);
        $segments = $this->segmentsFor($message);

        $capacity = $segments > 1
            ? $segments * $encoding->multiPartLimit()
            : $encoding->singlePartLimit();

        return [
            'encoding' => $encoding,
            'units' => $units,
            'segments' => $segments,
            'remaining' => max($capacity - $units, 0),
        ];
    }

    /**
     * Split the message into its individual UTF-8 characters.
     *
     * @return list<string>
     */
    protected function characters(string $value): array
    {
        /** @var list<string> $characters */
        $characters = mb_str_split($value, 1, 'UTF-8');

        return $characters;
    }
}
