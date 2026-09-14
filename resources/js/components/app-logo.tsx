import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-lg">
                <AppLogoIcon className="size-4" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="font-display mb-0.5 truncate leading-tight font-semibold tracking-tight">
                    {name}
                </span>
            </div>
        </>
    );
}
