/**
 * Build-time base path of the application.
 *
 * This app is sometimes served from a sub-directory of its host (ServBay's
 * `/gwosevo-omnicast/public`) and sometimes from the root of a domain
 * (`https://omnicast.sysup360.com`), so every URL the frontend emits has to
 * carry this prefix -- empty when it owns the domain root.
 *
 * Set `VITE_APP_BASE_PATH` in the .env file of the deployment, keeping it in
 * sync with the path of APP_URL (and ASSET_URL). `vite.config.ts` reads the
 * same variable to rewrite the root-relative route URLs that Wayfinder
 * generates, so a rebuild is required after changing it.
 */
export const appBasePath = (import.meta.env.VITE_APP_BASE_PATH ?? '').replace(
    /\/+$/,
    '',
);

/**
 * Prefix a root-relative application path with the deployment base path.
 */
export const withBasePath = (path: string): string => `${appBasePath}${path}`;
