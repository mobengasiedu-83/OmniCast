import { useEffect, useRef, useState } from 'react';

const SCRIPT_SRC =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

type TurnstileApi = {
    render: (container: HTMLElement, options: Record<string, unknown>) => string;
    remove: (widgetId: string) => void;
};

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

let scriptPromise: Promise<TurnstileApi> | null = null;

/**
 * Load Cloudflare's Turnstile script once, however many widgets ask for it.
 */
function loadTurnstile(): Promise<TurnstileApi> {
    if (window.turnstile) {
        return Promise.resolve(window.turnstile);
    }

    scriptPromise ??= new Promise<TurnstileApi>((resolve, reject) => {
        const script = document.createElement('script');

        script.src = SCRIPT_SRC;
        script.async = true;
        script.defer = true;

        script.onload = () => {
            if (window.turnstile) {
                resolve(window.turnstile);

                return;
            }

            reject(new Error('Turnstile loaded without exposing its API.'));
        };

        script.onerror = () =>
            reject(new Error('Turnstile could not be loaded.'));

        document.head.appendChild(script);
    });

    return scriptPromise;
}

/**
 * The Cloudflare Turnstile challenge that stands in front of the sign-in form.
 *
 * The widget solves the challenge and hands back a single use token, which
 * travels with the form as `cf-turnstile-response` and is verified server side
 * by Fortify. It is only rendered when the platform has keys configured, so a
 * local install still shows a plain form.
 */
export default function TurnstileWidget({ siteKey }: { siteKey: string }) {
    const container = useRef<HTMLDivElement | null>(null);
    const widgetId = useRef<string | null>(null);
    const [token, setToken] = useState('');
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        let cancelled = false;

        loadTurnstile()
            .then((turnstile) => {
                if (cancelled || container.current === null) {
                    return;
                }

                widgetId.current = turnstile.render(container.current, {
                    sitekey: siteKey,
                    theme: 'auto',
                    callback: (value: string) => setToken(value),
                    'expired-callback': () => setToken(''),
                    'error-callback': () => setToken(''),
                });
            })
            .catch(() => {
                if (!cancelled) {
                    setFailed(true);
                }
            });

        return () => {
            cancelled = true;

            if (widgetId.current !== null) {
                window.turnstile?.remove(widgetId.current);
                widgetId.current = null;
            }
        };
    }, [siteKey]);

    return (
        <div className="grid gap-2">
            <div ref={container} className="min-h-[65px]" />
            <input
                type="hidden"
                name="cf-turnstile-response"
                value={token}
                readOnly
            />
            {failed && (
                <p className="text-xs text-destructive">
                    The security check could not be loaded. Refresh the page to
                    try again.
                </p>
            )}
        </div>
    );
}
