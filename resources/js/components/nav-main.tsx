import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavGroup } from '@/types';

export function NavMain({ groups }: { groups: NavGroup[] }) {
    const { currentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <>
            {groups.map((group) => (
                <SidebarGroup key={group.title ?? 'main'} className="px-2 py-0">
                    {group.title ? (
                        <SidebarGroupLabel>{group.title}</SidebarGroupLabel>
                    ) : null}
                    <SidebarMenu>
                        {group.items.map((item) => {
                            const active =
                                item.isActive ??
                                (item.activeWhen?.some((prefix) =>
                                    currentUrl.startsWith(prefix),
                                ) ||
                                    isCurrentOrParentUrl(item.href));

                            return (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={active}
                                        tooltip={{ children: item.title }}
                                    >
                                        <Link
                                            href={item.href}
                                            prefetch
                                            data-tour={item.dataTour}
                                        >
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
