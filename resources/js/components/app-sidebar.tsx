import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    CalendarClock,
    Clapperboard,
    FolderOpen,
    HelpCircle,
    Image,
    LayoutDashboard,
    LayoutTemplate,
    MapPin,
    Monitor,
    Palette,
    Radio,
    Settings,
    Shield,
    Users,
} from 'lucide-react';
import { useMemo } from 'react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { WorkspaceSwitcher } from '@/components/workspace-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import {
    analytics,
    brand_kit,
    dashboard,
    help,
    locations,
    media,
    playlists,
    publishing,
    schedules,
    screen_designs,
    screens,
    settings as appSettings,
    team,
    templates,
} from '@/routes/app';
import { dashboard as adminDashboard } from '@/routes/admin';
import { ProductLabels } from '@/lib/product-labels';
import type { NavGroup, NavItem } from '@/types';

export function AppSidebar() {
    const { auth, workspace } = usePage().props;
    const permissions = workspace?.permissions;
    const isPlatformStaff = Boolean(
        auth.user?.platform_role === 'super_admin' ||
        auth.user?.platform_role === 'platform_admin' ||
        auth.user?.is_admin,
    );

    const navGroups = useMemo((): NavGroup[] => {
        const accountItems: NavItem[] = [
            {
                title: 'Team',
                href: team(),
                icon: Users,
            },
            {
                title: 'Settings',
                href: appSettings(),
                icon: Settings,
            },
        ];

        const insightItems: NavItem[] = [];
        if (permissions?.can_view_analytics) {
            insightItems.push({
                title: 'Analytics',
                href: analytics(),
                icon: BarChart3,
            });
        }

        const displayItems: NavItem[] = [];

        if (permissions?.can_manage_playlists) {
            displayItems.push({
                title: 'Playlists',
                href: playlists(),
                icon: FolderOpen,
            });
        }

        if (permissions?.can_manage_schedules) {
            displayItems.push({
                title: 'Schedules',
                href: schedules(),
                icon: CalendarClock,
            });
        }

        if (permissions?.can_publish_content) {
            displayItems.push({
                title: 'Publishing',
                href: publishing(),
                icon: Radio,
            });
        }

        if (
            permissions?.can_manage_screens ||
            permissions?.can_publish_content
        ) {
            displayItems.push({
                title: ProductLabels.pairedNav,
                href: screens(),
                icon: Monitor,
            });
        }

        if (permissions?.can_manage_locations) {
            displayItems.push({
                title: 'Locations',
                href: locations(),
                icon: MapPin,
            });
        }

        const groups: NavGroup[] = [
            {
                items: [
                    {
                        title: 'Dashboard',
                        href: dashboard(),
                        icon: LayoutDashboard,
                    },
                ],
            },
            {
                title: 'Create',
                items: [
                    {
                        title: ProductLabels.screenDesignPlural,
                        href: screen_designs(),
                        icon: Clapperboard,
                    },
                    {
                        title: 'Templates',
                        href: templates(),
                        icon: LayoutTemplate,
                    },
                    {
                        title: 'Media',
                        href: media(),
                        icon: Image,
                    },
                    {
                        title: 'Brand Kit',
                        href: brand_kit(),
                        icon: Palette,
                    },
                ],
            },
        ];

        if (displayItems.length > 0) {
            groups.push({
                title: 'Display',
                items: displayItems,
            });
        }

        if (insightItems.length > 0) {
            groups.push({ title: 'Insights', items: insightItems });
        }

        groups.push({
            title: 'Account',
            items: accountItems,
        });

        return groups;
    }, [permissions]);

    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            data-test="customer-sidebar"
        >
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <WorkspaceSwitcher />
            </SidebarHeader>

            <SidebarContent className="gap-2 overflow-y-auto">
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter className="gap-1">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild tooltip="Help & Support">
                            <Link href={help()} prefetch data-test="nav-help">
                                <HelpCircle className="size-4" />
                                <span>Help & Support</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    {isPlatformStaff ? (
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild tooltip="Admin Portal">
                                <Link
                                    href={adminDashboard()}
                                    prefetch
                                    data-test="nav-admin-portal"
                                >
                                    <Shield className="size-4" />
                                    <span>Admin Portal</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ) : null}
                </SidebarMenu>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
