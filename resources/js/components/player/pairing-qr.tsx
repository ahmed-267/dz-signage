import { qrImageUrl } from '@/lib/qrcode';
import { cn } from '@/lib/utils';

type PairingQrProps = {
    url: string;
    size?: number;
    className?: string;
    'data-test'?: string;
};

/**
 * Renders a QR code for the player pairing URL.
 * Uses QRServer image API in Phase 5 (see `@/lib/qrcode`).
 */
export function PairingQr({
    url,
    size = 240,
    className,
    'data-test': dataTest = 'player-qr',
}: PairingQrProps) {
    return (
        <img
            src={qrImageUrl(url, size)}
            alt="Scan to open pairing page"
            width={size}
            height={size}
            data-test={dataTest}
            className={cn('rounded-lg bg-white p-2 shadow-sm', className)}
        />
    );
}
