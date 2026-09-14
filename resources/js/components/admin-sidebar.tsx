import { Link } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    Boxes,
    Building2,
    CreditCard,
    FileText,
    FolderOpen,
    HeartPulse,
    LayoutDashboard,
    LayoutTemplate,
    Megaphone,
    Monitor,
    Receipt,
    ScrollText,
    Settings,
    Shield,
    Ticket,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
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
import {
    announcements,
    audit_logs,
    categories,
    dashboard,
    errors,
    invoices,
    media,
    payments,
    plans,
    publishing_jobs,
    scheduling_jobs,
    screen_health,
    screens,
    settings,
    subscriptions,
    system_health,
    templates,
    tickets,
    trials,
    users,
    widgets,
    workspaces,
} from '@/routes/admin';
import type { NavGroup } from '@/types';

const navGroups: NavGroup[] = [
    {
        title: 'Overview',
        items: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutDashboard,
            },
        ],
    },
    {
        title: 'Customers',
        items: [
            {
                title: 'Workspaces',
                href: workspaces(),
                icon: Building2,
            },
            {
                title: 'Users',
                href: users(),
                icon: Users,
            },
            {
                title: 'Screens',
                href: screens(),
                icon: Monitor,
            },
            {
                title: 'Trials',
                href: trials(),
                icon: Shield,
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
                href: plans(),
                icon: Wallet,
            },
            {
                title: 'Payments',
                href: payments(),
                icon: Receipt,
            },
            {
                title: 'Invoices',
                href: invoices(),
                icon: FileText,
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
            {
                title: 'Categories',
                href: categories(),
                icon: FolderOpen,
            },
            {
                title: 'Widgets',
                href: widgets(),
                icon: Boxes,
            },
            {
                title: 'Media',
                href: media(),
                icon: FolderOpen,
            },
        ],
    },
    {
        title: 'Operations',
        items: [
            {
                title: 'Screen Health',
                href: screen_health(),
                icon: HeartPulse,
            },
            {
                title: 'Publishing Jobs',
                href: publishing_jobs(),
                icon: Activity,
            },
            {
                title: 'Scheduling Jobs',
                href: scheduling_jobs(),
                icon: Activity,
            },
            {
                title: 'Errors',
                href: errors(),
                icon: AlertTriangle,
            },
            {
                title: 'System Health',
                href: system_health(),
                icon: HeartPulse,
            },
        ],
    },
    {
        title: 'Support',
        items: [
            {
                title: 'Tickets',
                href: tickets(),
                icon: Ticket,
            },
            {
                title: 'Announcements',
                href: announcements(),
                icon: Megaphone,
            },
        ],
    },
    {
        title: 'Platform',
        items: [
            {
                title: 'Audit Logs',
                href: audit_logs(),
                icon: ScrollText,
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
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                        <p className="text-muted-foreground px-2 pt-1 text-[11px] font-medium tracking-wide uppercase group-data-[collapsible=icon]:hidden">
                            Super Admin
                        </p>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-2 overflow-y-auto">
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
