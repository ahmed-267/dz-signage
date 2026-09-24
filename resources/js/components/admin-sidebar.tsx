import { Link, usePage } from '@inertiajs/react';
import {
    ChevronLeft,
    CreditCard,
    HeartPulse,
    LayoutDashboard,
    LayoutTemplate,
    MessageCircle,
    Settings,
    Shield,
    Users,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { ProductBrand } from '@/lib/product-brand';
import { dashboard as appDashboard } from '@/routes/app';
import {
    dashboard,
    screen_health,
    settings,
    subscriptions,
    support,
    templates,
    workspaces,
} from '@/routes/admin';
import type { NavGroup } from '@/types';

/**
 * Super Admin / Platform Admin sidebar — high-level sections only.
 * Individual pages live as tabs inside each section.
 */
export function AdminSidebar() {
    const { auth } = usePage().props;
    const platformRole =
        typeof auth.user?.platform_role === 'string'
            ? auth.user.platform_role
            : null;
    const isSuperAdmin = platformRole === 'super_admin';
    const roleLabel =
        platformRole === 'platform_admin'
            ? 'Platform Admin'
            : platformRole === 'super_admin'
              ? 'Super Admin'
              : 'Platform';

    const navGroups: NavGroup[] = [
        {
            items: [
                {
                    title: 'Overview',
                    href: dashboard(),
                    icon: LayoutDashboard,
                    activeWhen: ['/admin/dashboard'],
                    // `/admin` exact is handled via href match on home route
                },
                {
                    title: 'Customers',
                    href: workspaces(),
                    icon: Users,
                    activeWhen: [
                        '/admin/workspaces',
                        '/admin/users',
                        '/admin/screens',
                    ],
                },
                {
                    title: 'Billing',
                    href: subscriptions(),
                    icon: CreditCard,
                    activeWhen: ['/admin/subscriptions', '/admin/invoices'],
                },
                {
                    title: 'Content',
                    href: templates(),
                    icon: LayoutTemplate,
                    activeWhen: ['/admin/templates'],
                },
                {
                    title: 'Operations',
                    href: screen_health(),
                    icon: HeartPulse,
                    activeWhen: [
                        '/admin/screen-health',
                        '/admin/publishing-jobs',
                        '/admin/system-health',
                        '/admin/errors',
                    ],
                },
                {
                    title: 'Support',
                    href: support(),
                    icon: MessageCircle,
                    activeWhen: ['/admin/support'],
                },
                ...(isSuperAdmin
                    ? [
                          {
                              title: 'Settings',
                              href: settings(),
                              icon: Settings,
                              activeWhen: [
                                  '/admin/settings',
                                  '/admin/feature-flags',
                                  '/admin/audit-log',
                              ],
                          },
                      ]
                    : []),
            ],
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset" data-test="admin-sidebar">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-amber-400 text-black">
                                    <AppLogoIcon className="size-4" />
                                </div>
                                <div className="ml-1 grid flex-1 text-left text-sm">
                                    <span className="font-display mb-0.5 truncate leading-tight font-semibold tracking-tight">
                                        {ProductBrand.adminName}
                                    </span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                        <p className="text-muted-foreground px-2 pt-1 text-[11px] font-medium tracking-wide uppercase group-data-[collapsible=icon]:hidden">
                            <Shield className="mr-1 inline size-3 text-amber-500" />
                            {roleLabel}
                        </p>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-2 overflow-y-auto">
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter className="gap-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild tooltip="Back to App">
                            <Link
                                href={appDashboard()}
                                prefetch
                                data-test="admin-back-to-app"
                            >
                                <ChevronLeft className="size-4" />
                                <span>Back to App</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
