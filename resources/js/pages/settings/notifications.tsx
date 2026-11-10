import { Head, useForm } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { urls } from '@/lib/urls';
import type {
    NotificationCategoryOption,
    NotificationChannelOption,
    NotificationPreferenceMap,
} from '@/types';

type PageProps = {
    preferences: NotificationPreferenceMap;
    categories: NotificationCategoryOption[];
    channels: NotificationChannelOption[];
};

type Channel = 'in_app' | 'email' | 'sms';

/**
 * The channel matrix for the signed-in account.
 *
 * A category muted on every channel stops the inbox row and external copies. The
 * platform still records the event it would have told you about — this screen
 * only decides what reaches you.
 */
export default function NotificationPreferences({
    preferences,
    categories,
    channels,
}: PageProps) {
    const form = useForm<{ preferences: NotificationPreferenceMap }>({
        preferences,
    });

    const toggle = (
        category: string,
        channel: Channel,
        value: boolean,
    ): void => {
        form.setData('preferences', {
            ...form.data.preferences,
            [category]: {
                ...form.data.preferences[category],
                [channel]: value,
            },
        });
    };

    return (
        <>
            <Head title="Notifications" />

            <Heading
                variant="small"
                title="Notifications"
                description="Choose which notification categories reach you and which channels deliver them."
                icon={Bell}
            />

            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(urls.account.notifications.update, {
                        preserveScroll: true,
                    });
                }}
            >
                <div className="divide-y divide-border/60 rounded-lg border">
                    <div className="hidden items-center justify-between gap-4 px-4 py-2 text-xs font-medium text-muted-foreground sm:flex">
                        <span>Category</span>
                        <span className="flex items-center gap-6">
                            {channels.map((channel) => (
                                <span
                                    key={channel.value}
                                    className="w-20 text-center"
                                >
                                    {channel.label}
                                </span>
                            ))}
                        </span>
                    </div>

                    {categories.map((category) => (
                        <div
                            key={category.value}
                            className="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div className="min-w-0 space-y-1">
                                <p className="font-medium">{category.label}</p>
                                <p className="text-sm text-muted-foreground">
                                    {category.description}
                                </p>
                            </div>

                            <div className="flex items-center gap-6 sm:justify-end">
                                {channels.map((channel) => {
                                    const key = channel.value as Channel;
                                    const checked =
                                        form.data.preferences[category.value]?.[
                                            key
                                        ] ?? false;

                                    return (
                                        <label
                                            key={`${category.value}-${channel.value}`}
                                            className="flex w-20 items-center justify-center gap-2 text-sm"
                                            title={channel.description}
                                        >
                                            <Checkbox
                                                checked={checked}
                                                onCheckedChange={(value) =>
                                                    toggle(
                                                        category.value,
                                                        key,
                                                        value === true,
                                                    )
                                                }
                                                aria-label={`${channel.label} for ${category.label}`}
                                            />
                                            <span className="sm:sr-only">
                                                {channel.label}
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </div>

                <p className="text-xs text-muted-foreground">
                    Billing and compliance notices are emailed by default; other
                    categories stay in the app until you enable another channel.
                    Platform announcements always reach your inbox.
                </p>

                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Saving…' : 'Save preferences'}
                </Button>
            </form>
        </>
    );
}
