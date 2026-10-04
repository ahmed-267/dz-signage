/**
 * Fire TV / Android TV / living-room shells. Used only for pairing layout
 * (10-foot type). Playback still uses the shared LayoutRenderer.
 */
export function isTvShell(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return /AFT|Silk\/|Android TV|BRAVIA|CrKey|SmartTV|HbbTV|TV Safari/i.test(
        window.navigator.userAgent,
    );
}
