import { renderSVG } from 'uqr';
import { cn } from '@/lib/utils';

type PairingQrProps = {
    url: string;
    size?: number;
    className?: string;
    'data-test'?: string;
};

/**
 * Same-origin SVG QR for the pairing URL. Fire TV / TWA must not depend on
 * a third-party QR image API. The PIN remains the primary pairing UX.
 */
export function PairingQr({
    url,
    size = 240,
    className,
    'data-test': dataTest = 'player-qr',
}: PairingQrProps) {
    const svg = renderSVG(url, {
        ecc: 'M',
        boostEcc: true,
        border: 2,
        pixelSize: 1,
        whiteColor: '#ffffff',
        blackColor: '#0b0f14',
    });

    return (
        <img
            src={`data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`}
            alt="Scan to open pairing page"
            width={size}
            height={size}
            data-test={dataTest}
            className={cn('rounded-lg bg-white p-2 shadow-sm', className)}
        />
    );
}
