import type { Auth } from '@/types/auth';
import type { BrandKitShared } from '@/types/brand-kit';
import type { WorkspaceContext } from '@/types/workspace';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            workspace: WorkspaceContext;
            flash: {
                success?: string | null;
                error?: string | null;
            };
            sidebarOpen: boolean;
            ai?: {
                available: boolean;
                feature_enabled: boolean;
                configured: boolean;
                provider: string;
                video_enabled: boolean;
                message: string | null;
            } | null;
            brandKit?: BrandKitShared;
            [key: string]: unknown;
        };
    }
}
