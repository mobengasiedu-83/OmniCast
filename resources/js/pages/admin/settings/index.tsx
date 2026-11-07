import { Form, Head, Link } from '@inertiajs/react';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { urls } from '@/lib/urls';
import { update } from '@/routes/admin/settings';

type Setting = {
    group: string;
    key: string;
    value: string | null;
    is_secret: boolean;
    has_value: boolean;
};

const guidance: Record<string, string> = {
    company: 'Global company identity and default values.',
    channels: 'Use keys such as sms.enabled and voice.enabled.',
    providers:
        'SMS OTP keys: sms.driver, sms.endpoint, sms.token, sms.username, sms.password, sms.sender_id. Drivers: txtconnect, kannel or generic_json. TxtConnect uses Bearer token auth.',
    countries:
        'Set enabled to comma-separated ISO codes such as GH,NG,US. Leave blank to allow every supported country.',
    rate_limits:
        'Values are positive requests per minute, e.g. login_attempts_per_minute or client_registrations_per_minute.',
    storage:
        'Use max_upload_kb and client_documents_disk. Client documents only use private disks.',
    feature_flags: 'Boolean values such as channels.sms or campaigns.email.',
    plans: 'Plan keys follow plan-code.group.setting-key, e.g. default.rate_limits.login_attempts_per_minute.',
};

export default function SettingsIndex({
    groups,
    settings,
}: {
    groups: string[];
    settings: Setting[];
}) {
    const rows = groups.flatMap((group) => {
        const existing = settings.filter((item) => item.group === group);
        return [
            ...existing,
            { group, key: '', value: '', is_secret: false, has_value: false },
        ];
    });
    return (
        <>
            <Head title="Platform settings" />
            <PageHeader
                title="Platform settings"
                description="Configure global, plan and client defaults. Secret values are encrypted and never returned to the browser."
                actions={
                    <>
                        <Button asChild variant="outline">
                            <Link href={urls.admin.settings.modules}>
                                Module availability
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={urls.admin.settings.plans}>
                                Assign client plans
                            </Link>
                        </Button>
                    </>
                }
            />
            <Form {...update.form()} className="space-y-6">
                {({ processing }) => (
                    <>
                        {rows.map((item, index) => (
                            <section
                                key={`${item.group}-${index}`}
                                className="grid gap-3 rounded-lg border p-4 md:grid-cols-4"
                            >
                                <input
                                    type="hidden"
                                    name={`settings[${index}][group]`}
                                    value={item.group}
                                />
                                <div>
                                    <Label>Group</Label>
                                    <p className="text-sm">
                                        {item.group.replaceAll('_', ' ')}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {guidance[item.group]}
                                    </p>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor={`key-${index}`}>
                                        Setting key
                                    </Label>
                                    <Input
                                        id={`key-${index}`}
                                        name={`settings[${index}][key]`}
                                        defaultValue={item.key}
                                        placeholder="e.g. sms.enabled"
                                        required
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor={`value-${index}`}>
                                        {item.is_secret
                                            ? 'Secret value'
                                            : 'Value'}
                                        {item.has_value && item.is_secret
                                            ? ' (saved; blank preserves it)'
                                            : ''}
                                    </Label>
                                    <Input
                                        id={`value-${index}`}
                                        name={`settings[${index}][value]`}
                                        defaultValue={item.value ?? ''}
                                        type={
                                            item.is_secret ? 'password' : 'text'
                                        }
                                        autoComplete="new-password"
                                    />
                                </div>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="hidden"
                                        name={`settings[${index}][is_secret]`}
                                        value="0"
                                    />
                                    <input
                                        id={`secret-${index}`}
                                        type="checkbox"
                                        name={`settings[${index}][is_secret]`}
                                        value="1"
                                        defaultChecked={item.is_secret}
                                    />
                                    <Label htmlFor={`secret-${index}`}>
                                        Encrypt as secret
                                    </Label>
                                </div>
                            </section>
                        ))}
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Save settings'}
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}
