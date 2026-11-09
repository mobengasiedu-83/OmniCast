import { Form, Head, Link } from '@inertiajs/react';
import {
    BookOpen,
    CreditCard,
    LifeBuoy,
    MessagesSquare,
    Send,
    ShieldCheck,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import ContactChannels from '@/components/support/contact-channels';
import TurnstileField from '@/components/support/turnstile-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/contact';
import { index as helpIndex, show as helpShow } from '@/routes/help';
import type {
    SupportArticleSummary,
    SupportAttachmentLimits,
    SupportCategoryOption,
    SupportContact,
} from '@/types';

type PageProps = {
    categories: SupportCategoryOption[];
    contact: SupportContact;
    articles: SupportArticleSummary[];
    attachments: SupportAttachmentLimits;
};

/**
 * An icon per shipped category, with a neutral fallback.
 *
 * The category list is configuration, so the icon is decoration rather than
 * meaning: an operator can add a category and it still looks deliberate.
 */
const icons: Record<string, LucideIcon> = {
    'customer-support': LifeBuoy,
    sales: MessagesSquare,
    'technical-assistance': Wrench,
    'compliance-privacy': ShieldCheck,
    'billing-wallet': CreditCard,
    'campaign-delivery': Send,
};

/**
 * The public Contact Us page.
 *
 * It serves both audiences it has: a prospective customer asking about price, and
 * an existing client reporting a delivery that failed. Both write the same form,
 * and the reason they pick decides which team is emailed and which priority the
 * conversation starts with.
 */
export default function Contact({
    categories,
    contact,
    articles,
    attachments,
}: PageProps) {
    const [category, setCategory] = useState(categories[0]?.slug ?? '');

    /*
    | An operator can deactivate every category, which would leave a required
    | select with nothing to choose. In that case the page falls back to the
    | confirmed contact details instead of a form nobody can submit.
    */
    if (categories.length === 0) {
        return (
            <>
                <Head title="Contact us" />

                <section className="mx-auto max-w-2xl py-16 text-center">
                    <h1 className="text-3xl font-semibold tracking-tight">
                        We are here to help
                    </h1>
                    <p className="mt-3 text-sm text-muted-foreground">
                        The contact form is being configured. In the meantime the
                        team can be reached directly, and the help centre already
                        has the answers.
                    </p>

                    <ContactChannels
                        contact={contact}
                        className="mt-6 text-left text-sm"
                    />

                    <Button className="mt-6" asChild>
                        <Link href={helpIndex()}>Browse the help centre</Link>
                    </Button>
                </section>
            </>
        );
    }

    return (
        <>
            <Head title="Contact us" />

            <section className="mx-auto max-w-3xl py-12 text-center">
                <span className="inline-flex items-center gap-2 rounded-full bg-brand-soft px-3 py-1 text-xs font-medium text-accent-foreground ring-1 ring-brand/20">
                    <LifeBuoy className="size-3.5" />
                    Contact
                </span>

                <h1 className="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl">
                    We are here to help
                </h1>

                <p className="mt-3 text-sm text-muted-foreground">
                    Questions about SMS, voice or email broadcasting? Tell us what
                    you need and the right team will answer — or read the help
                    centre first if you would rather find it yourself.
                </p>
            </section>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {categories.map((option) => {
                    const Icon = icons[option.slug] ?? LifeBuoy;
                    const selected = option.slug === category;

                    return (
                        <button
                            key={option.slug}
                            type="button"
                            onClick={() => setCategory(option.slug)}
                            aria-pressed={selected}
                            className={`group flex flex-col gap-2 rounded-xl border p-4 text-left transition-colors ${
                                selected
                                    ? 'border-brand/50 bg-brand-soft/60 ring-1 ring-brand/25'
                                    : 'border-border/70 bg-card/60 hover:border-brand/30'
                            }`}
                        >
                            <span className="flex size-9 items-center justify-center rounded-lg brand-gradient text-primary-foreground shadow-sm">
                                <Icon className="size-4" />
                            </span>

                            <span className="text-sm font-semibold">
                                {option.name}
                            </span>

                            {option.description && (
                                <span className="text-xs text-muted-foreground">
                                    {option.description}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>

            <div className="mt-10 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <section className="rounded-2xl border border-border/70 bg-card/70 p-6">
                    <h2 className="text-lg font-semibold tracking-tight">
                        Send us a message
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        One form, one team, one reference number. We reply to the
                        address you give us.
                    </p>

                    <Form
                        {...store.form()}
                        encType="multipart/form-data"
                        resetOnSuccess
                        className="mt-6 grid gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Full name *</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            autoComplete="name"
                                        />
                                        {errors.name && (
                                            <p className="text-xs text-destructive">
                                                {errors.name}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="email">
                                            Email address *
                                        </Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            required
                                            autoComplete="email"
                                        />
                                        {errors.email && (
                                            <p className="text-xs text-destructive">
                                                {errors.email}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="phone">Phone number</Label>
                                        <Input
                                            id="phone"
                                            name="phone"
                                            autoComplete="tel"
                                        />
                                        {errors.phone && (
                                            <p className="text-xs text-destructive">
                                                {errors.phone}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="company">
                                            Company / organisation
                                        </Label>
                                        <Input
                                            id="company"
                                            name="company"
                                            autoComplete="organization"
                                        />
                                        {errors.company && (
                                            <p className="text-xs text-destructive">
                                                {errors.company}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="category">
                                        How can we help? *
                                    </Label>
                                    <select
                                        id="category"
                                        name="category"
                                        required
                                        value={category}
                                        onChange={(event) =>
                                            setCategory(event.target.value)
                                        }
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        {categories.map((option) => (
                                            <option
                                                key={option.slug}
                                                value={option.slug}
                                            >
                                                {option.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.category && (
                                        <p className="text-xs text-destructive">
                                            {errors.category}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="subject">Subject *</Label>
                                    <Input
                                        id="subject"
                                        name="subject"
                                        required
                                        maxLength={200}
                                    />
                                    {errors.subject && (
                                        <p className="text-xs text-destructive">
                                            {errors.subject}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="message">Your message *</Label>
                                    <textarea
                                        id="message"
                                        name="message"
                                        required
                                        rows={6}
                                        minLength={20}
                                        placeholder="Tell us what you are trying to do, and what is happening instead."
                                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    />
                                    {errors.message && (
                                        <p className="text-xs text-destructive">
                                            {errors.message}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="attachments">
                                        Screenshots or files
                                    </Label>
                                    <input
                                        id="attachments"
                                        name="attachments[]"
                                        type="file"
                                        multiple
                                        accept={attachments.mimes
                                            .map((mime) => `.${mime}`)
                                            .join(',')}
                                        className="block w-full rounded-md border border-input bg-background px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-2 file:py-1 file:text-xs"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Up to {attachments.max_files} files,{' '}
                                        {Math.round(
                                            attachments.max_kilobytes / 1024,
                                        )}{' '}
                                        MB each.
                                    </p>
                                    {errors['attachments.0'] && (
                                        <p className="text-xs text-destructive">
                                            {errors['attachments.0']}
                                        </p>
                                    )}
                                </div>

                                <label className="flex items-start gap-2 text-xs text-muted-foreground">
                                    <input
                                        type="checkbox"
                                        name="consent"
                                        value="1"
                                        required
                                        className="mt-0.5 size-4 rounded border-input"
                                    />
                                    <span>
                                        I agree that my details may be used to
                                        answer this enquiry, as described in the
                                        privacy policy.
                                    </span>
                                </label>
                                {errors.consent && (
                                    <p className="text-xs text-destructive">
                                        {errors.consent}
                                    </p>
                                )}

                                <TurnstileField
                                    error={errors['cf-turnstile-response']}
                                />

                                <div className="flex flex-wrap items-center gap-3">
                                    <Button type="submit" disabled={processing}>
                                        <Send />
                                        Send message
                                    </Button>
                                    <p className="text-xs text-muted-foreground">
                                        {contact.response_time ??
                                            'We answer every message in the order it arrives.'}
                                    </p>
                                </div>
                            </>
                        )}
                    </Form>
                </section>

                <aside className="grid content-start gap-4">
                    <section className="rounded-2xl border border-border/70 bg-muted/30 p-5">
                        <h2 className="text-sm font-semibold tracking-tight">
                            Other ways to reach us
                        </h2>

                        <ContactChannels
                            contact={contact}
                            className="mt-3 text-sm"
                        />

                        {!contact.support_email &&
                            !contact.phone &&
                            !contact.whatsapp && (
                                <p className="mt-3 text-xs text-muted-foreground">
                                    Use the form and the team will reply to your
                                    address.
                                </p>
                            )}
                    </section>

                    <section className="rounded-2xl border border-border/70 bg-card/70 p-5">
                        <h2 className="flex items-center gap-2 text-sm font-semibold tracking-tight">
                            <BookOpen className="size-4 text-brand" />
                            Help centre
                        </h2>

                        <ul className="mt-3 grid gap-2 text-sm">
                            {articles.map((article) => (
                                <li key={article.slug}>
                                    <Link
                                        href={helpShow.url(article.slug)}
                                        className="hover:text-brand"
                                    >
                                        {article.title}
                                    </Link>
                                </li>
                            ))}
                        </ul>

                        <Button
                            variant="outline"
                            size="sm"
                            className="mt-4"
                            asChild
                        >
                            <Link href={helpIndex()}>Browse all articles</Link>
                        </Button>
                    </section>
                </aside>
            </div>

            <section className="mt-10 rounded-2xl border border-border/70 bg-card/60 p-6">
                <h2 className="text-lg font-semibold tracking-tight">
                    Frequently asked questions
                </h2>

                <div className="mt-4 grid gap-3">
                    {[
                        {
                            question: 'How soon will I hear back?',
                            answer:
                                contact.response_time ??
                                'Every enquiry is queued to the team that owns the subject, and urgent delivery problems are answered first.',
                        },
                        {
                            question: 'Do I need an account to write to you?',
                            answer:
                                'No. A prospective customer can ask about pricing or a demonstration with the form above. An existing client can open a trackable ticket from the support menu once signed in.',
                        },
                        {
                            question: 'How do I report unwanted messages?',
                            answer:
                                'Choose Compliance & privacy on the form and include the sending number, the message and when it arrived. Every report is reviewed against the acceptable use policy.',
                        },
                        {
                            question: 'Where can I follow a problem I already reported?',
                            answer:
                                'A client ticket is followed under Support → My tickets, where the reference number, the thread and the current status live.',
                        },
                    ].map((item) => (
                        <details
                            key={item.question}
                            className="rounded-xl border border-border/70 bg-background/60 p-4"
                        >
                            <summary className="cursor-pointer text-sm font-medium">
                                {item.question}
                            </summary>
                            <p className="mt-2 text-sm text-muted-foreground">
                                {item.answer}
                            </p>
                        </details>
                    ))}
                </div>
            </section>

        </>
    );
}
