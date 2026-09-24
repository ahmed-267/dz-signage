import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /** When set, item is active if the current path matches any prefix. */
    activeWhen?: string[];
    /** Optional product-tour / test hook. */
    dataTour?: string;
};

export type NavGroup = {
    title?: string;
    items: NavItem[];
};
