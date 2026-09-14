import { Link } from '@inertiajs/react';
import {
    BarChart3,
    CalendarClock,
    Clapperboard,
    CreditCard,
    FolderOpen,
    Image,
    LayoutDashboard,
    LayoutTemplate,
    MapPin,
    Monitor,
    Palette,
    Radio,
    Settings,
    Users,
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
    analytics,
    billing,
    brand_kit,
    dashboard,
    locations,
    media,
    playlists,
    publishing,
    schedules,
    screen_designs,
    screens,
    team,
    templates,
} from '@/routes/app';
import { edit as editProfile } from '@/routes/profile';
import type { NavGroup } from '@/types';

const navGroups: NavGroup[] = [
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
                title: 'Screen Designs',
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
    {
        title: 'Display',
        items: [
            {
                title: 'Playlists',
                href: playlists(),
                icon: FolderOpen,
            },
            {
                title: 'Schedules',
                href: schedules(),
                icon: CalendarClock,
            },
            {
                title: 'Publishing',
                href: publishing(),
                icon: Radio,
            },
            {
                title: 'Screens',
                href: screens(),
                icon: Monitor,
            },
            {
                title: 'Locations',
                href: locations(),
                icon: MapPin,
            },
        ],
    },
    {
        title: 'Insights',
        items: [
            {
                title: 'Analytics',
                href: analytics(),
                icon: BarChart3,
            },
        ],
    },
    {
        title: 'Account',
        items: [
            {
                title: 'Team',
                href: team(),
                icon: Users,
            },
            {
                title: 'Billing',
                href: billing(),
                icon: CreditCard,
            },
            {
                title: 'Settings',
                href: editProfile(),
                icon: Settings,
            },
        ],
    },
];

export function AppSidebar() {
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
