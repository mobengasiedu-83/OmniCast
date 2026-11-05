import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { urls } from '@/lib/urls';

type Props = {
    token: string;
    email: string;
    name: string | null;
    emailInUse: boolean;
    roleLabel: string;
    organisation: string | null;
    inviter: string | null;
    expiresAt: string | null;
    passwordRules: string;
    isValid: boolean;
};

export default function AcceptInvitation({
    token,
    email,
    name,
    emailInUse,
    roleLabel,
    organisation,
    inviter,
    expiresAt,
    passwordRules,
    isValid,
}: Props) {
    if (!isValid || emailInUse) {
        return (
            <>
                <Head title="Invitation" />

                <div className="space-y-4 text-center">
                    <p className="text-sm text-muted-foreground">
                        {emailInUse
                            ? 'An account already exists for this email address. Sign in instead.'
                            : 'This invitation has expired or been withdrawn. Ask an administrator for a new one.'}
                    </p>

                    <Button asChild variant="secondary">
                        <Link href={urls.login}>Go to sign in</Link>
                    </Button>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Accept invitation" />

            <div className="mb-6 space-y-1 text-center">
                <p className="text-sm text-muted-foreground">
                    {organisation
                        ? `${inviter ?? 'An administrator'} invited you to ${organisation}`
                        : `${inviter ?? 'An administrator'} invited you to the platform team`}
                </p>
                <p className="text-sm">
                    <span className="font-medium">{email}</span> · {roleLabel}
                </p>
                {expiresAt && (
                    <p className="text-xs text-muted-foreground">
                        Link expires {expiresAt}
                    </p>
                )}
            </div>

            <Form
                action={urls.invitations.accept(token)}
                method="post"
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Full name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={name ?? ''}
                                    required
                                    autoFocus
                                    autoComplete="name"
                                    placeholder="Your name"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoComplete="new-password"
                                    placeholder="Choose a password"
                                    passwordrules={passwordRules}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                    placeholder="Repeat the password"
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Activate account
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            <p className="mt-6 text-center text-sm text-muted-foreground">
                Already activated?{' '}
                <Link
                    href={urls.login}
                    className="text-foreground underline underline-offset-4"
                >
                    Sign in
                </Link>
            </p>
        </>
    );
}

AcceptInvitation.layout = {
    title: 'Accept your invitation',
    description: 'Set a password to activate your account',
};
