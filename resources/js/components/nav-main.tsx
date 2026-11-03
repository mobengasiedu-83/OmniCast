import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

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
            <SidebarGroupLabel className="text-muted-foreground/80 text-[10px] font-semibold tracking-[0.16em] uppercase">
                {label}
            </SidebarGroupLabel>

            <SidebarMenu className="gap-1">
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentUrl(item.href)}
                            tooltip={{ children: item.title }}
                            className="relative h-9 rounded-lg transition-[background-color,color,box-shadow] duration-200 data-[active=true]:bg-brand-soft data-[active=true]:text-accent-foreground data-[active=true]:font-semibold data-[active=true]:shadow-xs data-[active=true]:[&>svg]:text-brand data-[active=true]:before:bg-brand data-[active=true]:before:absolute data-[active=true]:before:inset-y-2 data-[active=true]:before:-left-2 data-[active=true]:before:w-1 data-[active=true]:before:rounded-full data-[active=true]:before:content-['']"
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}

