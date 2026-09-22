import { Link, router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, Plus } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import {
    create as createWorkspace,
    switchMethod,
} from '@/routes/app/workspaces';

export function WorkspaceSwitcher() {
    const { workspace } = usePage().props;
    const { isMobile, state } = useSidebar();
    const current = workspace?.current;
    const available = workspace?.available ?? [];

    if (!current) {
        return null;
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                            data-test="workspace-switcher"
                            aria-label="Switch business"
                        >
                            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center overflow-hidden rounded-lg">
                                {current.logo_url ? (
                                    <img
                                        src={current.logo_url}
                                        alt=""
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    <Building2 className="size-4" />
                                )}
                            </div>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-semibold">
                                    {current.name}
                                </span>
                                <span className="text-muted-foreground truncate text-xs">
                                    {workspace?.role_label}
                                </span>
                            </div>
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-64 rounded-lg"
                        align="start"
                        side={
                            isMobile
                                ? 'bottom'
                                : state === 'collapsed'
                                  ? 'right'
                                  : 'bottom'
                        }
                        sideOffset={4}
                    >
                        <DropdownMenuLabel className="text-muted-foreground text-xs">
                            Businesses
                        </DropdownMenuLabel>
                        {available.map((item) => (
                            <DropdownMenuItem
                                key={item.id}
                                className="gap-2 p-2"
                                data-test={`workspace-option-${item.id}`}
                                onClick={() => {
                                    if (item.id === current.id) {
                                        return;
                                    }

                                    router.post(
                                        switchMethod.url({
                                            workspace: item.id,
                                        }),
                                    );
                                }}
                            >
                                <div className="bg-muted flex size-6 items-center justify-center overflow-hidden rounded-md border">
                                    {item.logo_url ? (
                                        <img
                                            src={item.logo_url}
                                            alt=""
                                            className="size-full object-cover"
                                        />
                                    ) : (
                                        <Building2 className="size-3.5" />
                                    )}
                                </div>
                                <div className="flex flex-1 flex-col">
                                    <span className="truncate text-sm">
                                        {item.name}
                                    </span>
                                    <span className="text-muted-foreground text-xs">
                                        {item.role_label}
                                    </span>
                                </div>
                                {item.id === current.id ? (
                                    <Check className="size-4" />
                                ) : null}
                            </DropdownMenuItem>
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild className="gap-2 p-2">
                            <Link href={createWorkspace.url()}>
                                <div className="bg-muted flex size-6 items-center justify-center rounded-md border">
                                    <Plus className="size-4" />
                                </div>
                                <span className="text-sm">Create business</span>
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
