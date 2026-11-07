import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import ThemePicker from '@/components/theme-picker';
import { edit as editAppearance } from '@/routes/appearance';
import type { ThemeOption } from '@/types';

type Props = {
    themes: ThemeOption[];
    currentTheme: string;
    canChooseTheme: boolean;
};

export default function Appearance({
    themes,
    currentTheme,
    canChooseTheme,
}: Props) {
    return (
        <>
            <Head title="Appearance settings" />

            <h1 className="sr-only">Appearance settings</h1>

            <div className="space-y-8">
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Appearance settings"
                        description="Update the appearance settings for your account"
                    />
                    <AppearanceTabs />
                </div>

                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="Theme"
                        description={
                            canChooseTheme
                                ? 'Pick the colour theme for your whole organisation. Every teammate sees it on their next page load.'
                                : 'Colour themes are chosen by an organisation admin.'
                        }
                    />
                    <ThemePicker
                        themes={themes}
                        currentTheme={currentTheme}
                        canChooseTheme={canChooseTheme}
                    />
                </div>
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Appearance settings',
            href: editAppearance(),
        },
    ],
};
