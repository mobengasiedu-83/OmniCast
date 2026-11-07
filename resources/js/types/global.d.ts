import type { Auth } from '@/types/auth';
import type { ModuleAvailability } from '@/types/module';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            modules: ModuleAvailability;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
