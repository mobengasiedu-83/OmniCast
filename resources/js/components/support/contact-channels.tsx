import {
    Clock,
    ExternalLink,
    Mail,
    MapPin,
    MessageCircle,
    PhoneCall,
} from 'lucide-react';
import type { SupportContact } from '@/types';

/**
 * The ways to reach the team, as the business has confirmed them.
 *
 * A channel with no confirmed value is not rendered at all — the point of the
 * block is that everything in it is true, so a half-filled row is worse than no
 * row.
 */
export default function ContactChannels({
    contact,
    className,
}: {
    contact: SupportContact;
    className?: string;
}) {
    const channels = [
        (contact.support_email ?? contact.email)
            ? {
                  icon: Mail,
                  label: 'Email',
                  value: (contact.support_email ?? contact.email) as string,
                  href: `mailto:${contact.support_email ?? contact.email}`,
              }
            : null,
        contact.phone
            ? {
                  icon: PhoneCall,
                  label: 'Telephone',
                  value: contact.phone,
                  href: `tel:${contact.phone.replace(/[^+\d]/g, '')}`,
              }
            : null,
        contact.whatsapp
            ? {
                  icon: MessageCircle,
                  label: 'WhatsApp',
                  value: contact.whatsapp,
                  href: `https://wa.me/${contact.whatsapp.replace(/[^\d]/g, '')}`,
              }
            : null,
        contact.address
            ? {
                  icon: MapPin,
                  label: 'Office',
                  value: contact.address,
                  href: null,
              }
            : null,
        contact.hours
            ? {
                  icon: Clock,
                  label: 'Support hours',
                  value: contact.hours,
                  href: null,
              }
            : null,
    ].filter((channel): channel is NonNullable<typeof channel> => channel !== null);

    if (channels.length === 0 && !contact.response_time) {
        return null;
    }

    return (
        <div className={className}>
            <ul className="grid gap-3">
                {channels.map((channel) => {
                    const Icon = channel.icon;

                    return (
                        <li
                            key={channel.label}
                            className="flex items-start gap-3 rounded-lg border border-border/70 bg-card/60 p-3"
                        >
                            <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand ring-1 ring-brand/15">
                                <Icon className="size-4" />
                            </span>

                            <div className="min-w-0 space-y-0.5">
                                <p className="text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                                    {channel.label}
                                </p>

                                {channel.href ? (
                                    <a
                                        href={channel.href}
                                        target={
                                            channel.href.startsWith('http')
                                                ? '_blank'
                                                : undefined
                                        }
                                        rel="noreferrer"
                                        className="block text-sm break-words hover:text-brand"
                                    >
                                        {channel.value}
                                    </a>
                                ) : (
                                    <p className="text-sm break-words">
                                        {channel.value}
                                    </p>
                                )}
                            </div>
                        </li>
                    );
                })}
            </ul>

            {contact.response_time && (
                <p className="mt-3 text-xs text-muted-foreground">
                    {contact.response_time}
                </p>
            )}

            {contact.status_url && (
                <a
                    href={contact.status_url}
                    target="_blank"
                    rel="noreferrer"
                    className="mt-3 inline-flex items-center gap-1.5 text-xs font-medium text-brand hover:underline"
                >
                    Service status
                    <ExternalLink className="size-3" />
                </a>
            )}
        </div>
    );
}
