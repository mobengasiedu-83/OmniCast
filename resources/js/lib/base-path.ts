/**
 * This application is served from a sub-directory of its host, so every URL the
 * frontend emits has to carry this prefix. Keep it in sync with APP_URL (and
 * ASSET_URL) in the .env file -- `vite.config.ts` imports it to rewrite the
 * root-relative route URLs that Wayfinder generates.
 */
export const appBasePath = '/gwosevo-unified-app/public';

/**
 * Prefix a root-relative application path with the deployment base path.
 */
export const withBasePath = (path: string): string => `${appBasePath}${path}`;
