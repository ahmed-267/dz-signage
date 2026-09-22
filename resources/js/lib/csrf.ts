/**
 * Laravel's CSRF cookie, in the header shape `ValidateCsrfToken` expects.
 *
 * The Inertia router sends this automatically; the JSON endpoints that are
 * called with `fetch` (schedule conflicts) need it spelled out.
 */
export function csrfHeaders(): Record<string, string> {
    if (typeof document === 'undefined') {
        return {};
    }

    const match = /(?:^|;\s*)XSRF-TOKEN=([^;]*)/.exec(document.cookie);

    return match ? { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) } : {};
}
