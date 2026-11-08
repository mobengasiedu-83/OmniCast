import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuAction,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

/** The active/inactive styling shared by every top-level sidebar link. */
const menuButtonClassName =
    "relative h-9 rounded-lg transition-[background-color,color,box-shadow] duration-200 data-[active=true]:bg-brand-soft data-[active=true]:text-accent-foreground data-[active=true]:font-semibold data-[active=true]:shadow-xs data-[active=true]:[&>svg]:text-brand data-[active=true]:before:bg-brand data-[active=true]:before:absolute data-[active=true]:before:inset-y-2 data-[active=true]:before:-left-2 data-[active=true]:before:w-1 data-[active=true]:before:rounded-full data-[active=true]:before:content-['']";

/** A top-level entry whose children live in an expandable submenu. */
function NavMainGroup({ item }: { item: NavItem }) {
    const { isCurrentUrl } = useCurrentUrl();
    const children = item.children ?? [];
    const childIsActive = children.some((child) => isCurrentUrl(child.href));
    const [open, setOpen] = useState(childIsActive);

    useEffect(() => {
        if (childIsActive) {
            setOpen(true);
        }
    }, [childIsActive]);

    return (
        <Collapsible open={open} onOpenChange={setOpen} asChild>
            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    isActive={isCurrentUrl(item.href) || childIsActive}
                    tooltip={{ children: item.title }}
                    className={menuButtonClassName}
                >
                    <Link href={item.href} prefetch>
                        {item.icon && <item.icon />}
                        <span>{item.title}</span>
                    </Link>
                </SidebarMenuButton>

                <CollapsibleTrigger asChild>
                    <SidebarMenuAction
                        aria-label={`Toggle ${item.title} links`}
                        className="data-[state=open]:bg-sidebar-accent"
                    >
                        <ChevronRight
                            className={cn(
                                'size-4 transition-transform duration-200',
                                open && 'rotate-90',
                            )}
                        />
                    </SidebarMenuAction>
                </CollapsibleTrigger>

                <CollapsibleContent>
                    <SidebarMenuSub>
                        {children.map((child) => (
                            <SidebarMenuSubItem key={child.title}>
                                <SidebarMenuSubButton
                                    asChild
                                    isActive={isCurrentUrl(child.href)}
                                >
                                    <Link href={child.href} prefetch>
                                        {child.icon && <child.icon />}
                                        <span>{child.title}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

export function NavMain({
    items,
    label = 'Platform',
}: {
    items: NavItem[];
    label?: string;
}) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel className="text-[10px] font-semibold tracking-[0.16em] text-muted-foreground/80 uppercase">
                {label}
            </SidebarGroupLabel>

            <SidebarMenu className="gap-1">
                {items.map((item) =>
                    item.children && item.children.length > 0 ? (
                        <NavMainGroup key={item.title} item={item} />
                    ) : (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentUrl(item.href)}
                                tooltip={{ children: item.title }}
                                className={menuButtonClassName}
                            >
                                {item.external ? (
                                    /**
                                     * A plain anchor, not `<Link>`: the target opens in
                                     * its own tab, so there is no page to visit with the
                                     * router. Documentation leaves the app on purpose.
                                     */
                                    <a
                                        href={toUrl(item.href)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                    </a>
                                ) : (
                                    <Link href={item.href} prefetch>
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                    </Link>
                                )}
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}
