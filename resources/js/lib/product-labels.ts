/**
 * Customer-facing product labels.
 *
 * Screen = content created in RMSignage (formerly "Screen Design").
 * TV = physical paired playback device (sidebar: Paired TVs).
 * Internal models/routes may still use ScreenDesign / /app/screens for TVs.
 */
export const ProductLabels = {
    displaySingular: 'TV',
    displayPlural: 'TVs',
    pairedNav: 'Paired TVs',
    /** Content object shown in the library / editor / playlists. */
    screenDesignSingular: 'Screen',
    screenDesignPlural: 'Screens',
    createScreen: 'Create Screen',
    screenEditor: 'Screen Editor',
    screenName: 'Screen Name',
    playlistName: 'Playlist Name',
    scheduleName: 'Schedule Name',
    pairAction: 'Pair TV',
    publishTo: 'Publish to TV',
    publishToSelected: 'Publish to selected TVs',
    licences: 'TV licences',
    health: 'TV Health',
    availability: 'TV Availability',
    online: 'TVs Online',
} as const;

export function displayLabel(count = 1): string {
    return count === 1
        ? ProductLabels.displaySingular
        : ProductLabels.displayPlural;
}
