<?php

namespace App\Support\ScreenDesigns;

use App\Enums\ScreenDesignStatus;
use App\Models\ScreenDesign;

/**
 * Shared helpers for blank / abandoned Screen Design detection.
 * Used by library pruning, seeders, and duplicate guards.
 */
final class ScreenDesignContent
{
    /**
     * True when the schema has at least one element with real text, media, widget, or non-placeholder content.
     *
     * @param  array<string, mixed>|null  $schema
     */
    public static function isMeaningful(?array $schema): bool
    {
        if ($schema === null) {
            return false;
        }

        $elements = $schema['elements'] ?? null;
        if (! is_array($elements) || $elements === []) {
            return false;
        }

        foreach ($elements as $element) {
            if (! is_array($element)) {
                continue;
            }

            if (self::elementIsMeaningful($element)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Draft + empty/placeholder-only content + default-ish name or never edited.
     */
    public static function isAbandonedDraft(ScreenDesign $design): bool
    {
        if ($design->status !== ScreenDesignStatus::Draft) {
            return false;
        }

        $version = null;
        if ($design->relationLoaded('versions')) {
            $version = $design->versions->sortByDesc('version_number')->first();
        }
        $version ??= $design->latestVersion() ?? $design->publishedVersion;

        $schema = is_array($version?->schema) ? $version->schema : null;
        if (self::isMeaningful($schema)) {
            return false;
        }

        if (self::hasAbandonedNamePattern((string) $design->name)) {
            return true;
        }

        // Never meaningfully edited: empty AI / New * drafts with untouched timestamps.
        if (preg_match('/^(AI |New )/i', trim((string) $design->name)) !== 1) {
            return false;
        }

        if ($design->created_at === null || $design->updated_at === null) {
            return true;
        }

        return abs($design->created_at->diffInSeconds($design->updated_at)) < 5;
    }

    public static function hasAbandonedNamePattern(string $name): bool
    {
        $trimmed = trim($name);

        return preg_match('/^(Untitled|New Landscape|New Portrait|Blank)(\b|$)/i', $trimmed) === 1;
    }

    public static function defaultNameForOrientation(string $orientation): string
    {
        return strtolower($orientation) === 'portrait'
            ? 'New Portrait Design'
            : 'New Landscape Design';
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private static function elementIsMeaningful(array $element): bool
    {
        $type = strtolower((string) ($element['type'] ?? ''));
        $props = is_array($element['props'] ?? null) ? $element['props'] : [];
        $isPlaceholder = ($props['placeholder'] ?? false) === true;

        $mediaId = $props['mediaAssetId'] ?? null;
        $hasMedia = $mediaId !== null && $mediaId !== '' && (int) $mediaId > 0;

        $text = trim((string) ($props['text'] ?? ''));
        $hasText = $text !== '';

        $widgetType = trim((string) ($props['widgetType'] ?? ''));
        $hasWidget = $type === 'widget' && $widgetType !== '';

        if ($hasMedia || $hasWidget || $hasText) {
            return true;
        }

        // Empty placeholders (image/logo/text slots with no content) are not meaningful.
        if ($isPlaceholder) {
            return false;
        }

        // Non-placeholder decorative elements (shapes, filled rects, etc.) count as content.
        return in_array($type, ['shape', 'rect', 'circle', 'line', 'group'], true)
            || ($type !== '' && $type !== 'text' && $type !== 'image' && $type !== 'logo' && $type !== 'video');
    }
}
