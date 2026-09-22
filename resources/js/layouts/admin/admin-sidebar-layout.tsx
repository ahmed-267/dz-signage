import { Link } from '@inertiajs/react';
import { ChevronLeft, Shield } from 'lucide-react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AdminSidebar } from '@/components/admin-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { dashboard as appDashboard } from '@/routes/app';
import type { AppLayoutProps } from '@/types';

export default function AdminSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AdminSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <div
                    className="border-border bg-card flex h-12 shrink-0 items-center justify-between border-b px-4 md:px-6"
                    data-test="admin-topbar"
                >
                    <div className="text-muted-foreground flex items-center gap-2 text-sm font-medium">
                        <Shield className="size-4 text-amber-500" />
                        <span className="font-mono text-xs tracking-wide uppercase">
                            Admin Portal
                        </span>
                    </div>
                    <Link
                        href={appDashboard()}
                        className="text-muted-foreground hover:text-foreground flex items-center gap-1 text-xs transition-colors"
                        data-test="admin-topbar-back"
                    >
                        <ChevronLeft className="size-3.5" />
                        Back to App
                    </Link>
                </div>
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
