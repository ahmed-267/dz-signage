<?php

namespace App\Actions\BrandKit;

use App\Models\BrandKit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateBrandKit
{
    /**
     * @param  array{
     *     name?: string|null,
     *     tagline?: string|null,
     *     primary_color: string,
     *     secondary_color: string,
     *     accent_color: string,
     *     background_color: string,
     *     text_color: string,
     *     heading_font: string,
     *     body_font: string,
     *     logo_media_asset_id?: int|null,
     *     secondary_logo_media_asset_id?: int|null
     * }  $data
     */
    public function handle(User $user, BrandKit $brandKit, array $data): BrandKit
    {
        return DB::transaction(function () use ($brandKit, $data) {
            $normalize = fn (string $hex): string => $brandKit->normalizeHex($hex) ?? $hex;

            $brandKit->fill([
                'name' => array_key_exists('name', $data)
                    ? (filled($data['name'] ?? null) ? trim((string) $data['name']) : null)
                    : $brandKit->name,
                'tagline' => array_key_exists('tagline', $data)
                    ? (filled($data['tagline'] ?? null) ? trim((string) $data['tagline']) : null)
                    : $brandKit->tagline,
                'primary_color' => $normalize($data['primary_color']),
                'secondary_color' => $normalize($data['secondary_color']),
                'accent_color' => $normalize($data['accent_color']),
                'background_color' => $normalize($data['background_color']),
                'text_color' => $normalize($data['text_color']),
                'heading_font' => $data['heading_font'],
                'body_font' => $data['body_font'],
                'logo_media_asset_id' => $data['logo_media_asset_id'] ?? null,
                'secondary_logo_media_asset_id' => $data['secondary_logo_media_asset_id'] ?? null,
            ]);

            $brandKit->save();

            return $brandKit->fresh(['logo', 'secondaryLogo']) ?? $brandKit;
        });
    }
}
