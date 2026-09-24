import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

type SharedFlash = {
    success?: string | null;
    error?: string | null;
    publish_result?: {
        content_name?: string;
        content_kind?: string;
        screen_ids?: number[];
        screen_names?: string[];
    } | null;
};

export function useFlashToast(): void {
    useEffect(() => {
        const removeNavigate = router.on('navigate', (event) => {
            const flash = (event.detail.page.props as { flash?: SharedFlash })
                .flash;

            if (flash?.publish_result?.screen_names?.length) {
                const names = flash.publish_result.screen_names.join(', ');
                const content = flash.publish_result.content_name ?? 'Content';
                toast.success(`${content} → ${names}`, {
                    description:
                        'Published successfully. Open Paired TVs to confirm sync.',
                    action: flash.publish_result.screen_ids?.[0]
                        ? {
                              label: 'View TV',
                              onClick: () => {
                                  router.visit(
                                      `/app/screens/${flash.publish_result!.screen_ids![0]}`,
                                  );
                              },
                          }
                        : undefined,
                });
            } else if (flash?.success) {
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
