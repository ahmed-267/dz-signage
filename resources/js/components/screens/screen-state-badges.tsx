import { Badge } from '@/components/ui/badge';

export function healthBadgeVariant(
    health: string,
): 'success' | 'warning' | 'neutral' {
    if (health === 'healthy') {
        return 'success';
    }

    return health === 'attention' ? 'warning' : 'neutral';
}

export function contentSyncBadgeVariant(
    sync: string,
): 'success' | 'warning' | 'neutral' {
    if (sync === 'up_to_date') {
        return 'success';
    }

    return sync === 'out_of_sync' ? 'warning' : 'neutral';
}

type Props = {
    id: number;
    operationalStatus: string;
    pairingState: string;
    networkState: string;
};

/**
 * Operational status, pairing and network are three independent axes —
 * never collapse them into a single status. Text labels (not colour alone)
 * carry the meaning.
 */
export function ScreenStateBadges({
    id,
    operationalStatus,
    pairingState,
    networkState,
}: Props) {
    return (
        <div className="flex flex-wrap gap-1.5">
            <Badge
                variant={operationalStatus === 'active' ? 'success' : 'neutral'}
                data-test={`screen-status-${id}`}
            >
                {operationalStatus === 'active' ? 'Active' : 'Inactive'}
            </Badge>
            <Badge
                variant={pairingState === 'connected' ? 'success' : 'warning'}
                data-test={`screen-pairing-${id}`}
            >
                {pairingState === 'connected' ? 'Connected' : 'Disconnected'}
            </Badge>
            <Badge
                variant={networkState === 'online' ? 'info' : 'neutral'}
                data-test={`screen-network-${id}`}
            >
                {networkState === 'online' ? 'Online' : 'Offline'}
            </Badge>
        </div>
    );
}
