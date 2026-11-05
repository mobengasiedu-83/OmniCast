import { Form, Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';
import { create, password, store, verify } from '@/routes/client-registration';

type Props = {
    step: 'details' | 'otp' | 'password';
    email?: string | null;
    otpChannel?: 'email' | 'sms' | null;
    services: { value: string; label: string }[];
    accountTypes: {
        value: 'business' | 'private';
        label: string;
        identificationTypes: { value: string; label: string }[];
    }[];
    countries: { code: string; name: string; timezones: string[] }[];
    defaultCountry: string;
    defaultTimezone: string;
    passwordRules: string;
};

export default function RegisterClient({
    step,
    email,
    otpChannel,
    services,
    accountTypes,
    countries,
    defaultCountry,
    defaultTimezone,
    passwordRules,
}: Props) {
    const [accountType, setAccountType] = useState<'business' | 'private'>(
        'business',
    );
    const [countryCode, setCountryCode] = useState(defaultCountry);
    const country = useMemo(
        () => countries.find((item) => item.code === countryCode),
        [countries, countryCode],
    );
    const defaultZone = country?.timezones.includes(defaultTimezone)
        ? defaultTimezone
        : (country?.timezones[0] ?? defaultTimezone);
    const [timezone, setTimezone] = useState(defaultZone);
    return (
        <>
            <Head title="Create client account" />
            <PageHeader
                title={
                    step === 'details'
                        ? 'Create your client account'
                        : step === 'otp'
                          ? 'Verify your contact'
                          : 'Create a password'
                }
                description={
                    step === 'details'
                        ? 'Submit your organisation details. Email and phone are required.'
                        : step === 'otp'
                          ? otpChannel === 'sms'
                              ? 'Enter the six-digit code sent by SMS to your phone.'
                              : `Enter the six-digit code sent to ${email}.`
                          : 'Your verification code is confirmed. Set a password to finish signup.'
                }
            />
            {step === 'details' && (
                <Form
                    {...store.form()}
                    encType="multipart/form-data"
                    className="mt-6 grid gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="account_type">
                                    Account type
                                </Label>
                                <select
                                    id="account_type"
                                    name="account_type"
                                    value={accountType}
                                    onChange={(event) =>
                                        setAccountType(
                                            event.target.value as
                                                | 'business'
                                                | 'private',
                                        )
                                    }
                                    required
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    {accountTypes.map((type) => (
                                        <option
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.account_type} />
                            </div>
                            {accountType === 'business' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="company_name">
                                        Business name
                                    </Label>
                                    <Input
                                        id="company_name"
                                        name="company_name"
                                        required
                                    />
                                    <InputError message={errors.company_name} />
                                </div>
                            )}
                            <div className="grid gap-2">
                                <Label htmlFor="identification_type">
                                    Identification document
                                </Label>
                                <select
                                    id="identification_type"
                                    name="identification_type"
                                    required
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    {accountTypes
                                        .find(
                                            (type) =>
                                                type.value === accountType,
                                        )
                                        ?.identificationTypes.map((type) => (
                                            <option
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </option>
                                        ))}
                                </select>
                                <InputError
                                    message={errors.identification_type}
                                />
                                <p className="text-sm text-muted-foreground">
                                    Upload one document only. A business name is
                                    required for business accounts; private
                                    accounts do not need one.
                                </p>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="identification_document">
                                    Upload identification (PDF/JPG/PNG)
                                </Label>
                                <Input
                                    id="identification_document"
                                    name="identification_document"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    required
                                />
                                <InputError
                                    message={errors.identification_document}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="contact_name">
                                    Contact name
                                </Label>
                                <Input
                                    id="contact_name"
                                    name="contact_name"
                                    autoComplete="name"
                                    required
                                />
                                <InputError message={errors.contact_name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    autoComplete="email"
                                    required
                                />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="phone">Phone number</Label>
                                <Input
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    autoComplete="tel"
                                    placeholder="+233…"
                                    required
                                />
                                <InputError message={errors.phone} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="country">Country</Label>
                                <select
                                    id="country"
                                    name="country"
                                    value={countryCode}
                                    onChange={(event) => {
                                        const nextCode = event.target.value;
                                        const nextCountry = countries.find(
                                            (item) => item.code === nextCode,
                                        );
                                        setCountryCode(nextCode);
                                        setTimezone(
                                            nextCountry?.timezones[0] ?? 'UTC',
                                        );
                                    }}
                                    required
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option value="" disabled>
                                        Select your country
                                    </option>
                                    {countries.map((item) => (
                                        <option
                                            key={item.code}
                                            value={item.code}
                                        >
                                            {item.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.country} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="timezone">Timezone</Label>
                                <select
                                    id="timezone"
                                    name="timezone"
                                    value={timezone}
                                    onChange={(event) =>
                                        setTimezone(event.target.value)
                                    }
                                    required
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    {(
                                        country?.timezones ?? [defaultTimezone]
                                    ).map((zone) => (
                                        <option key={zone} value={zone}>
                                            {zone}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.timezone} />
                            </div>
                            <fieldset className="grid gap-2">
                                <legend className="text-sm font-medium">
                                    Services requested
                                </legend>
                                {services.map((service) => (
                                    <label
                                        key={service.value}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            name="services[]"
                                            value={service.value}
                                        />
                                        {service.label}
                                    </label>
                                ))}
                                {errors.services && (
                                    <InputError message={errors.services} />
                                )}
                            </fieldset>
                            <div className="grid gap-2">
                                <Label htmlFor="website">
                                    Website (optional)
                                </Label>
                                <Input id="website" name="website" type="url" />
                            </div>
                            <fieldset className="grid gap-2">
                                <legend className="text-sm font-medium">
                                    Send verification code by
                                </legend>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="radio"
                                        name="otp_channel"
                                        value="email"
                                        defaultChecked
                                    />
                                    Email
                                </label>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="radio"
                                        name="otp_channel"
                                        value="sms"
                                    />
                                    SMS to the phone number above
                                </label>
                                {errors.otp_channel && (
                                    <InputError message={errors.otp_channel} />
                                )}
                            </fieldset>
                            <Button disabled={processing}>
                                {processing
                                    ? 'Sending code…'
                                    : 'Continue to verification'}
                            </Button>
                        </>
                    )}
                </Form>
            )}
            {step === 'otp' && (
                <Form {...verify.form()} className="mt-6 grid gap-4">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="otp">
                                    6-digit verification code
                                </Label>
                                <Input
                                    id="otp"
                                    name="otp"
                                    inputMode="numeric"
                                    pattern="[0-9]{6}"
                                    maxLength={6}
                                    autoComplete="one-time-code"
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.otp} />
                            </div>
                            <Button disabled={processing}>
                                {processing ? 'Verifying…' : 'Verify code'}
                            </Button>
                            <Link
                                className="text-center text-sm underline"
                                href={create()}
                            >
                                Start again
                            </Link>
                        </>
                    )}
                </Form>
            )}
            {step === 'password' && (
                <Form {...password.form()} className="mt-6 grid gap-4">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                    required
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
                                    autoComplete="new-password"
                                    required
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>
                            <Button disabled={processing}>
                                {processing
                                    ? 'Creating account…'
                                    : 'Create account'}
                            </Button>
                            <Link
                                className="text-center text-sm underline"
                                href={create()}
                            >
                                Start again
                            </Link>
                        </>
                    )}
                </Form>
            )}
            <p className="mt-6 text-center text-sm text-muted-foreground">
                Already have an account?{' '}
                <TextLink href={login()}>Sign in</TextLink>
            </p>
        </>
    );
}

RegisterClient.layout = {
    title: 'Client account setup',
    description: 'Verify your contact details and set a password',
};
