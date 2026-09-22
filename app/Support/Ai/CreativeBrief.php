<?php

namespace App\Support\Ai;

/**
 * Structured creative brief — server-side only; never sent to the browser raw.
 */
final readonly class CreativeBrief
{
    /**
     * @param  list<string>  $brandColors
     * @param  array{heading: string, body: string}|null  $brandFonts
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $prompt,
        public string $purpose,
        public string $style,
        public string $orientation,
        public string $industry,
        public bool $useBrandKit,
        public ?string $brandName = null,
        public ?string $brandTagline = null,
        public array $brandColors = [],
        public ?array $brandFonts = null,
        public ?int $logoMediaAssetId = null,
        public string $headline = '',
        public string $subheadline = '',
        public string $cta = '',
        public string $body = '',
        public string $mood = 'professional',
        public array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'prompt' => $this->prompt,
            'purpose' => $this->purpose,
            'style' => $this->style,
            'orientation' => $this->orientation,
            'industry' => $this->industry,
            'use_brand_kit' => $this->useBrandKit,
            'brand_name' => $this->brandName,
            'brand_tagline' => $this->brandTagline,
            'brand_colors' => $this->brandColors,
            'brand_fonts' => $this->brandFonts,
            'logo_media_asset_id' => $this->logoMediaAssetId,
            'headline' => $this->headline,
            'subheadline' => $this->subheadline,
            'cta' => $this->cta,
            'body' => $this->body,
            'mood' => $this->mood,
            'meta' => $this->meta,
        ];
    }

    public function headingFont(): string
    {
        return $this->brandFonts['heading'] ?? 'Outfit';
    }

    public function bodyFont(): string
    {
        return $this->brandFonts['body'] ?? 'system-ui';
    }

    public function primaryColor(): string
    {
        return $this->brandColors[0] ?? '#0F172A';
    }

    public function accentColor(): string
    {
        return $this->brandColors[2] ?? ($this->brandColors[1] ?? '#0D9488');
    }

    public function backgroundColor(): string
    {
        return $this->brandColors[3] ?? '#0F172A';
    }

    public function textColor(): string
    {
        return $this->brandColors[4] ?? '#F8FAFC';
    }
}
