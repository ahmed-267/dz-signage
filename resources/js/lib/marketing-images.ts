/**
 * Local marketing assets under /public/images/marketing (no remote hotlinks).
 * Curated Unsplash JPEGs — sync with `php artisan marketing:generate-assets`.
 * Attribution: docs/DEMO_MEDIA_SOURCES.md
 */
export const MARKETING_IMAGES = {
    cafe: {
        src: '/images/marketing/cafe-iced-coffee.jpg',
        width: 1600,
        height: 1068,
        alt: 'Café iced latte promotional photograph',
    },
    cafe_interior: {
        src: '/images/marketing/cafe-interior.jpg',
        width: 1600,
        height: 1097,
        alt: 'Café interior photograph',
    },
    retail: {
        src: '/images/marketing/retail-sale.jpg',
        width: 1600,
        height: 1068,
        alt: 'Retail fashion store photograph',
    },
    corporate: {
        src: '/images/marketing/corporate-welcome.jpg',
        width: 1600,
        height: 1068,
        alt: 'Corporate office interior photograph',
    },
    hotel: {
        src: '/images/marketing/hotel-lobby.jpg',
        width: 1600,
        height: 1067,
        alt: 'Hotel lobby photograph',
    },
    masjid: {
        src: '/images/marketing/masjid-prayer.jpg',
        width: 1066,
        height: 1600,
        alt: 'Masjid interior photograph',
    },
    gym: {
        src: '/images/marketing/gym-classes.jpg',
        width: 1600,
        height: 1067,
        alt: 'Gym fitness floor photograph',
    },
    restaurant: {
        src: '/images/marketing/restaurant-special.jpg',
        width: 1600,
        height: 1067,
        alt: 'Restaurant plated food photograph',
    },
    events: {
        src: '/images/marketing/events-stage.jpg',
        width: 1600,
        height: 1067,
        alt: 'Conference stage photograph',
    },
    education: {
        src: '/images/marketing/education-campus.jpg',
        width: 1600,
        height: 996,
        alt: 'Education campus photograph',
    },
    healthcare: {
        src: '/images/marketing/healthcare-clinic.jpg',
        width: 1600,
        height: 1089,
        alt: 'Healthcare clinic photograph',
    },
    property: {
        src: '/images/marketing/property-home.jpg',
        width: 1600,
        height: 1067,
        alt: 'Residential property exterior photograph',
    },
    landscape: {
        src: '/images/marketing/landscape-hills.jpg',
        width: 1600,
        height: 1067,
        alt: 'Mountain landscape photograph',
    },
} as const;

export type MarketingImageKey = keyof typeof MARKETING_IMAGES;

export type MarketingImageProps = {
    imageKey: MarketingImageKey;
    /** Hero primary only — skip lazy, request high fetch priority. */
    priority?: boolean;
    className?: string;
    alt?: string;
};

/** Optimised <img> for landing mocks — lazy below the fold by default. */
export function marketingImageAttrs({
    imageKey,
    priority = false,
    alt,
}: Pick<MarketingImageProps, 'imageKey' | 'priority' | 'alt'>) {
    const asset = MARKETING_IMAGES[imageKey];

    return {
        src: asset.src,
        alt: alt ?? asset.alt,
        width: asset.width,
        height: asset.height,
        decoding: 'async' as const,
        ...(priority
            ? { fetchPriority: 'high' as const }
            : { loading: 'lazy' as const }),
    };
}
