/**
 * The SMS budget a body is billed on, mirroring `App\Enums\SmsEncoding`.
 */
export type SmsEncoding = 'gsm7' | 'unicode';

/**
 * The GSM 03.38 default alphabet.
 */
const gsm7Basic =
    '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';

/**
 * Characters outside the basic alphabet that still fit in GSM-7, billed as two
 * septets each.
 */
const gsm7Extended = '^{}\\[~]|€';

const basicCharacters = new Set(Array.from(gsm7Basic));
const extendedCharacters = new Set(Array.from(gsm7Extended));

/**
 * Characters that fit in the first part and in every part of a longer message.
 *
 * Concatenated messages spend septets (GSM-7) or characters (Unicode) on the
 * user data header, which is why a single SMS holds 160 but each part of a
 * multipart SMS only holds 153. A space counts like any other character.
 */
export const smsLimits: Record<
    SmsEncoding,
    { readonly singlePart: number; readonly multiPart: number }
> = {
    gsm7: { singlePart: 160, multiPart: 153 },
    unicode: { singlePart: 70, multiPart: 67 },
};

/**
 * The encoding the carrier will bill the message on.
 *
 * The server applies the same rule in `App\Services\SmsSegmenter::encodingFor`.
 */
export function smsEncodingFor(message: string): SmsEncoding {
    return Array.from(message).every(
        (character) =>
            basicCharacters.has(character) || extendedCharacters.has(character),
    )
        ? 'gsm7'
        : 'unicode';
}

/**
 * The billable units of the message: septets for GSM-7, characters for Unicode.
 */
export function smsUnitsFor(message: string): number {
    const characters = Array.from(message);

    if (smsEncodingFor(message) === 'unicode') {
        return characters.length;
    }

    return characters.reduce(
        (units, character) =>
            units + (extendedCharacters.has(character) ? 2 : 1),
        0,
    );
}

/**
 * How many SMS the message is sent as. An empty body still reports one SMS,
 * because that is what the composer is budgeting for.
 */
export function smsSegmentsFor(message: string): number {
    const units = smsUnitsFor(message);

    if (units === 0) {
        return 1;
    }

    const limits = smsLimits[smsEncodingFor(message)];

    return units <= limits.singlePart ? 1 : Math.ceil(units / limits.multiPart);
}

export type SmsAnalysis = {
    encoding: SmsEncoding;
    units: number;
    segments: number;
    /** How many units the message may still grow without adding an SMS. */
    remaining: number;
    /** The unit budget of the parts the message currently fills. */
    capacity: number;
};

/**
 * The full segmentation breakdown the composer shows under the message box.
 *
 * This mirrors `App\Services\SmsSegmenter::analyze`, so the counter predicts
 * what the campaign is billed and appears as in the outbox.
 */
export function analyzeSms(message: string): SmsAnalysis {
    const encoding = smsEncodingFor(message);
    const units = smsUnitsFor(message);
    const segments = smsSegmentsFor(message);
    const limits = smsLimits[encoding];

    const capacity =
        segments > 1 ? segments * limits.multiPart : limits.singlePart;

    return {
        encoding,
        units,
        segments,
        remaining: Math.max(capacity - units, 0),
        capacity,
    };
}
