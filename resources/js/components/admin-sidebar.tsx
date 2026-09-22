import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    ChevronLeft,
    CreditCard,
    FileText,
    Flag,
    HardDrive,
    HeartPulse,
    LayoutDashboard,
    LayoutTemplate,
    MessageCircle,
    Monitor,
    Package,
    Receipt,
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
import { ProductLabels } from '@/lib/product-labels';
import { dashboard as appDashboard } from '@/routes/app';
import {
    audit_log,
    dashboard,
    errors,
    feature_flags,
    invoices,
    publishing_jobs,
    screen_health,
    screens,
    settings,
    subscriptions,
    support,
    system_health,
    templates,
    users,
    workspaces,
} from '@/routes/admin';
import { plans as billingPlans } from '@/routes/admin/subscriptions';
import type { NavGroup } from '@/types';

const navGroups: NavGroup[] = [
    {
        items: [
            {
                title: 'Overview',
                href: dashboard(),
                icon: LayoutDashboard,
            },
        ],
    },
    {
        title: 'Customers',
        items: [
            {
                title: 'Businesses',
                href: workspaces(),
                icon: Users,
            },
            {
                title: 'Users',
                href: users(),
                icon: Users,
            },
            {
                title: ProductLabels.displayPlural,
                href: screens(),
                icon: Monitor,
            },
        ],
    },
    {
        title: 'Billing',
        items: [
            {
                title: 'Subscriptions',
                href: subscriptions(),
                icon: CreditCard,
            },
            {
                title: 'Plans',
                href: billingPlans(),
                icon: Package,
            },
            {
                title: 'Invoices',
                href: invoices(),
                icon: Receipt,
            },
        ],
    },
    {
        title: 'Content',
        items: [
            {
                title: 'Templates',
                href: templates(),
                icon: LayoutTemplate,
            },
        ],
    },
    {
        title: 'Operations',
        items: [
            {
                title: ProductLabels.health,
                href: screen_health(),
                icon: Activity,
            },
            {
                title: 'Publishing Jobs',
                href: publishing_jobs(),
                icon: HardDrive,
            },
            {
                title: 'System Health',
                href: system_health(),
                icon: HeartPulse,
            },
            {
                title: 'Errors',
                href: errors(),
                icon: AlertTriangle,
            },
        ],
    },
    {
        title: 'Support',
        items: [
            {
                title: 'Support Requests',
                href: support(),
                icon: MessageCircle,
            },
            {
                title: 'Audit Log',
                href: audit_log(),
                icon: FileText,
            },
        ],
    },
    {
        title: 'Platform',
        items: [
            {
                title: 'Feature Flags',
                href: feature_flags(),
                icon: Flag,
            },
            {
                title: 'Settings',
                href: settings(),
                icon: Settings,
            },
        ],
    },
];

export function AdminSidebar() {
    const { auth } = usePage().props;
    const roleLabel =
        typeof auth.user?.platform_role === 'string'
            ? auth.user.platform_role === 'platform_admin'
                ? 'Admin'
                : 'Super Admin'
            : 'Platform';

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
