import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

/**
 * Replays the page entrance animation whenever Inertia swaps components.
 *
 * The key is the component name rather than the URL so partial reloads that
 * only change the query string (filters, pagination) keep their local state
 * instead of remounting the page.
 */
export default function PageTransition({ children }: { children: ReactNode }) {
    const { component } = usePage();

    return (
        <div key={component} className="animate-fade-in-up">
            {children}
        </div>
    );
}
