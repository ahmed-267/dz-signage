import { usePage } from '@inertiajs/react';
import { Plus, Share, Smartphone } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    capturePwaInstallEvents,
    clearDeferredPwaPrompt,
    dismissPwaInstallPrompt,
    getDeferredPwaPrompt,
    isIosChrome,
    isIosDevice,
    isPwaInstallDismissed,
    isStandaloneDisplay,
    registerAppServiceWorker,
    subscribePwaInstall,
} from '@/lib/pwa';
import { ProductBrand } from '@/lib/product-brand';

type ProductOnboardingShared = {
    active?: boolean;
} | null;

export default function PwaInstallPrompt() {
    const onboarding = usePage().props.productOnboarding as
        | ProductOnboardingShared
        | undefined;
    const tourActive = Boolean(onboarding?.active);
    const [open, setOpen] = useState(false);
    const [mode, setMode] = useState<'install' | 'ios' | null>(null);
    const [installing, setInstalling] = useState(false);

    useEffect(() => {
        capturePwaInstallEvents();
        void registerAppServiceWorker();

        if (isStandaloneDisplay() || isPwaInstallDismissed()) {
            return;
        }

        const syncMode = () => {
            if (getDeferredPwaPrompt()) {
                setMode('install');
                return;
            }

            if (isIosDevice()) {
                setMode('ios');
            }
        };

        syncMode();
        const unsubscribe = subscribePwaInstall(syncMode);
        const timeout = window.setTimeout(syncMode, 1500);

        return () => {
            unsubscribe();
            window.clearTimeout(timeout);
        };
    }, []);

    useEffect(() => {
        if (tourActive || !mode || isStandaloneDisplay()) {
            setOpen(false);
            return;
        }

        const timeout = window.setTimeout(() => {
            setOpen(true);
        }, 1800);

        return () => {
            window.clearTimeout(timeout);
        };
    }, [mode, tourActive]);

    function close(snooze: boolean) {
        setOpen(false);
        if (snooze) {
            dismissPwaInstallPrompt();
        }
    }

    async function install() {
        const promptEvent = getDeferredPwaPrompt();
        if (!promptEvent) {
            close(true);
            return;
        }

        setInstalling(true);
        try {
            await promptEvent.prompt();
            const choice = await promptEvent.userChoice;
            clearDeferredPwaPrompt();
            close(choice.outcome !== 'accepted');
        } catch {
            close(true);
        } finally {
            setInstalling(false);
        }
    }

    const iosChrome = isIosChrome();

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    close(true);
                }
            }}
        >
            <DialogContent
                data-test="pwa-install-dialog"
                className="overflow-hidden rounded-2xl p-0 sm:max-w-md"
            >
                <div className="from-primary/20 via-background to-background relative bg-gradient-to-b px-6 pt-8 pb-4 text-center">
                    <div className="bg-background text-primary ring-primary/20 mx-auto flex size-16 items-center justify-center rounded-2xl shadow-lg ring-1">
                        <AppLogoIcon className="size-9" />
                    </div>
                    <DialogHeader className="mt-5 items-center text-center">
                        <DialogTitle className="font-display text-xl tracking-tight">
                            Install {ProductBrand.name}
                        </DialogTitle>
                        <DialogDescription className="text-pretty">
                            Open your workspace in one tap — full screen, home
                            screen icon, no app store.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                {mode === 'ios' ? (
                    <ol className="text-muted-foreground space-y-3 px-6 pb-2 text-sm">
                        {iosChrome ? (
                            <li className="bg-muted/60 rounded-xl px-3 py-2 text-left">
                                iPhone and iPad can only add this app from{' '}
                                <span className="text-foreground font-medium">
                                    Safari
                                </span>
                                . Open this page there, then follow the steps
                                below.
                            </li>
                        ) : null}
                        <li className="flex items-start gap-3 text-left">
                            <span className="bg-primary/10 text-primary flex size-8 shrink-0 items-center justify-center rounded-full">
                                <Share className="size-4" aria-hidden />
                            </span>
                            <span>
                                Tap the{' '}
                                <span className="text-foreground font-medium">
                                    Share
                                </span>{' '}
                                button in Safari (square with an arrow pointing
                                up).
                            </span>
                        </li>
                        <li className="flex items-start gap-3 text-left">
                            <span className="bg-primary/10 text-primary flex size-8 shrink-0 items-center justify-center rounded-full">
                                <Plus className="size-4" aria-hidden />
                            </span>
                            <span>
                                Scroll and tap{' '}
                                <span className="text-foreground font-medium">
                                    Add to Home Screen
                                </span>
                                .
                            </span>
                        </li>
                        <li className="flex items-start gap-3 text-left">
                            <span className="bg-primary/10 text-primary flex size-8 shrink-0 items-center justify-center rounded-full">
                                <Smartphone className="size-4" aria-hidden />
                            </span>
                            <span>
                                Tap{' '}
                                <span className="text-foreground font-medium">
                                    Add
                                </span>
                                . {ProductBrand.name} appears on your Home
                                Screen like any other app.
                            </span>
                        </li>
                    </ol>
                ) : null}

                <div className="flex flex-col gap-2 px-6 pt-2 pb-6 sm:flex-row sm:justify-end">
                    {mode === 'install' ? (
                        <>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => close(true)}
                            >
                                Not now
                            </Button>
                            <Button
                                type="button"
                                onClick={() => {
                                    void install();
                                }}
                                disabled={installing}
                            >
                                {installing ? 'Installing…' : 'Install app'}
                            </Button>
                        </>
                    ) : (
                        <Button type="button" onClick={() => close(true)}>
                            Got it
                        </Button>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
