import { usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TurnstileWidget from '@/components/turnstile-widget';

type TurnstileProps = {
    turnstile?: { enabled: boolean; site_key: string | null };
};

/**
 * The Cloudflare Turnstile challenge on a support form.
 *
 * The keys come from the shared page props, so a form never has to pass them and
 * a local checkout without keys simply renders nothing. The widget's hidden input
 * travels with the submission, and the server refuses a token that does not
 * verify.
 */
export default function TurnstileField({ error }: { error?: string }) {
    const { turnstile } = usePage<TurnstileProps>().props;
    const siteKey =
        turnstile?.enabled === true ? (turnstile.site_key ?? null) : null;

    if (siteKey === null) {
        return null;
    }

    return (
        <div className="grid gap-2">
            <TurnstileWidget siteKey={siteKey} />
            <InputError message={error} />
        </div>
    );
}
