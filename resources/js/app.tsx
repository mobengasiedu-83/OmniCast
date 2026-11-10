import { createInertiaApp } from '@inertiajs/react';
import { ConfirmDialogProvider } from '@/components/confirm-dialog';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import MarketingLayout from '@/layouts/marketing-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'SysUp360 OmniCast';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'pricing':
            case name === 'user-manual':
            case name === 'api-docs':
            case name === 'legal/index':
            case name === 'legal/show':
            case name === 'contact':
            case name === 'help/index':
            case name === 'help/show':
                return MarketingLayout;
            case name === 'error':
            case name === 'contacts/dnd':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                <ConfirmDialogProvider>
                    {app}
                    <Toaster />
                </ConfirmDialogProvider>
            </TooltipProvider>
        );
    },
    progress: {
        color: '#f47d11',
    },
});

// This will set light / dark mode on load...
initializeTheme();
