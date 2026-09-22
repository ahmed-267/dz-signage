/**
 * Canonical customer-visible product name.
 * Keep in sync with App\Support\ProductBrand::NAME — do not read VITE_APP_NAME
 * (stale env can reintroduce legacy branding into document titles).
 */
export const ProductBrand = {
    name: 'RMSignage',
    adminName: 'RMSignage Admin',
} as const;
