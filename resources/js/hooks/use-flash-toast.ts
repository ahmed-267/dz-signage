import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

type SharedFlash = {
    success?: string | null;
    error?: string | null;
};

export function useFlashToast(): void {
    useEffect(() => {
        const removeNavigate = router.on('navigate', (event) => {
            const flash = (event.detail.page.props as { flash?: SharedFlash })
                .flash;

            if (flash?.success) {
                toast.success(flash.success);
            }

            if (flash?.error) {
                toast.error(flash.error);
            }
        });

        const removeFlash = router.on('flash', (event) => {
            const detail = (event as CustomEvent).detail?.flash;
            const data = detail?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            toast[data.type](data.message);
        });

        return () => {
            removeNavigate();
            removeFlash();
        };
    }, []);
}
