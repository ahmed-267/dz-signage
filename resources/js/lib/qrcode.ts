/**
 * Phase 5 QR helper — no new npm dependencies.
 *
 * Generates a scannable QR image URL for a pairing link via the public
 * QRServer API. Pairing code remains the primary UX; QR is a convenience.
 * Replace with an in-app encoder if offline-first QR becomes a requirement.
 */
export function qrImageUrl(data: string, size = 240): string {
    const params = new URLSearchParams({
        size: `${size}x${size}`,
        data,
        margin: '8',
    });

    return `https://api.qrserver.com/v1/create-qr-code/?${params.toString()}`;
}
