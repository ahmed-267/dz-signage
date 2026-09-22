import { WidgetShell } from '@/components/widgets/widget-shell';
import type { InfoCardWidgetConfig } from '@/lib/widgets/types';
import type { LayoutElementProps, LayoutMediaMap } from '@/types/layout-schema';
import { cn } from '@/lib/utils';

type InfoCardWidgetProps = {
    config: InfoCardWidgetConfig;
    elementProps?: LayoutElementProps;
    mediaMap?: LayoutMediaMap;
};

function resolveMediaUrl(
    mediaMap: LayoutMediaMap | undefined,
    mediaAssetId: string | number | null | undefined,
): string | null {
    if (mediaMap == null || mediaAssetId == null || mediaAssetId === '') {
        return null;
    }
    const entry =
        mediaMap[mediaAssetId] ?? mediaMap[String(mediaAssetId)] ?? null;
    return entry?.url ?? null;
}

export function InfoCardWidget({
    config,
    elementProps,
    mediaMap,
}: InfoCardWidgetProps) {
    const mediaUrl = resolveMediaUrl(
        mediaMap,
        config.mediaAssetId ?? elementProps?.mediaAssetId,
    );
    const split = config.layout === 'split';

    return (
        <WidgetShell
            props={elementProps}
            className={cn(
                'gap-2',
                split ? 'flex-row items-stretch' : 'justify-center',
            )}
        >
            {mediaUrl ? (
                <div
                    className={cn(
                        'overflow-hidden rounded-md',
                        split ? 'h-full w-2/5 shrink-0' : 'mb-1 h-24 w-full',
                    )}
                >
                    <img
                        src={mediaUrl}
                        alt=""
                        className="h-full w-full object-cover"
                        draggable={false}
                    />
                </div>
            ) : null}
            <div
                className={cn(
                    'flex min-w-0 flex-col gap-1',
                    split ? 'flex-1 justify-center' : '',
                )}
            >
                {config.heading ? (
                    <div className="leading-tight">{config.heading}</div>
                ) : null}
                {config.subheading ? (
                    <div
                        className="opacity-80"
                        style={{ fontSize: '0.45em', fontWeight: 500 }}
                    >
                        {config.subheading}
                    </div>
                ) : null}
                {config.value ? (
                    <div
                        className="tabular-nums"
                        style={{ fontSize: '1.1em', fontWeight: 700 }}
                    >
                        {config.value}
                    </div>
                ) : null}
                {config.body ? (
                    <div
                        className="opacity-85"
                        style={{ fontSize: '0.42em', fontWeight: 400 }}
                    >
                        {config.body}
                    </div>
                ) : null}
                {config.footer ? (
                    <div
                        className="mt-auto opacity-70"
                        style={{ fontSize: '0.34em', fontWeight: 500 }}
                    >
                        {config.footer}
                    </div>
                ) : null}
            </div>
        </WidgetShell>
    );
}
