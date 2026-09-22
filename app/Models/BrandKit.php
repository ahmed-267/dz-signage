<?php

namespace App\Models;

use Database\Factories\BrandKitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One Brand Kit per Workspace — colours, fonts, and logos for design + AI.
 *
 * @property int $id
 * @property int $workspace_id
 * @property string|null $name
 * @property string|null $tagline
 * @property string $primary_color
 * @property string $secondary_color
 * @property string $accent_color
 * @property string $background_color
 * @property string $text_color
 * @property string $heading_font
 * @property string $body_font
 * @property int|null $logo_media_asset_id
 * @property int|null $secondary_logo_media_asset_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BrandKit extends Model
{
    /** @use HasFactory<BrandKitFactory> */
    use HasFactory;

    public const DEFAULT_PRIMARY = '#0F172A';

    public const DEFAULT_SECONDARY = '#334155';

    public const DEFAULT_ACCENT = '#0D9488';

    public const DEFAULT_BACKGROUND = '#FFFFFF';

    public const DEFAULT_TEXT = '#0F172A';

    public const DEFAULT_HEADING_FONT = 'Outfit';

    public const DEFAULT_BODY_FONT = 'system-ui';

    /**
     * Safe font stacks / names used by Brand Kit + Screen Design editor.
     *
     * @return list<string>
     */
    public static function allowedFonts(): array
    {
        return [
            'Outfit',
            'Georgia',
            'system-ui',
            'ui-sans-serif',
            'ui-serif',
            'ui-monospace',
            'JetBrains Mono',
        ];
    }

    protected $fillable = [
        'workspace_id',
        'name',
        'tagline',
        'primary_color',
        'secondary_color',
        'accent_color',
        'background_color',
        'text_color',
        'heading_font',
        'body_font',
        'logo_media_asset_id',
        'secondary_logo_media_asset_id',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaultsFor(Workspace $workspace): array
    {
        return [
            'name' => $workspace->name,
            'tagline' => null,
            'primary_color' => self::DEFAULT_PRIMARY,
            'secondary_color' => self::DEFAULT_SECONDARY,
            'accent_color' => self::DEFAULT_ACCENT,
            'background_color' => self::DEFAULT_BACKGROUND,
            'text_color' => self::DEFAULT_TEXT,
            'heading_font' => self::DEFAULT_HEADING_FONT,
            'body_font' => self::DEFAULT_BODY_FONT,
            'logo_media_asset_id' => null,
            'secondary_logo_media_asset_id' => null,
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'logo_media_asset_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function secondaryLogo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'secondary_logo_media_asset_id');
    }

    /**
     * Normalised colour list for AI design prompts.
     *
     * @return list<string>
     */
    public function brandColors(): array
    {
        $colors = [
            $this->normalizeHex($this->primary_color),
            $this->normalizeHex($this->secondary_color),
            $this->normalizeHex($this->accent_color),
            $this->normalizeHex($this->background_color),
            $this->normalizeHex($this->text_color),
        ];

        return array_values(array_unique(array_filter($colors)));
    }

    public function logoUrl(): ?string
    {
        $this->loadMissing('logo');

        return $this->logo?->publicUrl();
    }

    /**
     * Expand #RGB → #RRGGBB and uppercase for consistent storage/AI.
     */
    public function normalizeHex(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^#([0-9A-Fa-f]{3})$/', $value, $m) === 1) {
            $hex = $m[1];

            return '#'.strtoupper($hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]);
        }

        if (preg_match('/^#([0-9A-Fa-f]{6})$/', $value, $m) === 1) {
            return '#'.strtoupper($m[1]);
        }

        return null;
    }

    /**
     * CSS font-family value for layout schema props.
     */
    public static function fontStack(string $font): string
    {
        return match ($font) {
            'Outfit' => 'Outfit, system-ui, sans-serif',
            'Georgia' => 'Georgia, ui-serif, serif',
            'JetBrains Mono' => 'JetBrains Mono, ui-monospace, monospace',
            'ui-sans-serif' => 'ui-sans-serif, system-ui, sans-serif',
            'ui-serif' => 'ui-serif, Georgia, serif',
            'ui-monospace' => 'ui-monospace, monospace',
            default => 'system-ui, sans-serif',
        };
    }
}
