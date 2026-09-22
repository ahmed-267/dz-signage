<?php

namespace App\Http\Requests\BrandKit;

use App\Enums\MediaType;
use App\Models\BrandKit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandKitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $brandKit = $this->route('brandKit');
        $user = $this->user();

        if (! $brandKit instanceof BrandKit || ! $user) {
            return false;
        }

        $workspace = $user->currentWorkspace;
        if (! $workspace || (int) $brandKit->workspace_id !== (int) $workspace->id) {
            abort(404);
        }

        return $user->can('update', $brandKit);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $workspaceId = $this->user()?->current_workspace_id;
        $hex = ['required', 'string', 'regex:/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/'];

        $logoRule = [
            'nullable',
            'integer',
            Rule::exists('media_assets', 'id')->where(function ($query) use ($workspaceId): void {
                $query->where('workspace_id', $workspaceId)
                    ->whereIn('type', [MediaType::Image->value, MediaType::Logo->value]);
            }),
        ];

        return [
            'name' => ['nullable', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'primary_color' => $hex,
            'secondary_color' => $hex,
            'accent_color' => $hex,
            'background_color' => $hex,
            'text_color' => $hex,
            'heading_font' => ['required', 'string', Rule::in(BrandKit::allowedFonts())],
            'body_font' => ['required', 'string', Rule::in(BrandKit::allowedFonts())],
            'logo_media_asset_id' => $logoRule,
            'secondary_logo_media_asset_id' => $logoRule,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_color.regex' => 'Colours must be hex values like #RGB or #RRGGBB.',
            'secondary_color.regex' => 'Colours must be hex values like #RGB or #RRGGBB.',
            'accent_color.regex' => 'Colours must be hex values like #RGB or #RRGGBB.',
            'background_color.regex' => 'Colours must be hex values like #RGB or #RRGGBB.',
            'text_color.regex' => 'Colours must be hex values like #RGB or #RRGGBB.',
            'logo_media_asset_id.exists' => 'Logo must be an image or logo Media asset from this workspace.',
            'secondary_logo_media_asset_id.exists' => 'Logo must be an image or logo Media asset from this workspace.',
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'logo_media_asset_id',
            'secondary_logo_media_asset_id',
        ] as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    /**
     * @return array{
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
     * }
     */
    public function brandKitData(): array
    {
        $validated = $this->validated();

        return [
            'name' => array_key_exists('name', $validated) ? ($validated['name'] !== null ? (string) $validated['name'] : null) : null,
            'tagline' => array_key_exists('tagline', $validated) ? ($validated['tagline'] !== null ? (string) $validated['tagline'] : null) : null,
            'primary_color' => (string) $validated['primary_color'],
            'secondary_color' => (string) $validated['secondary_color'],
            'accent_color' => (string) $validated['accent_color'],
            'background_color' => (string) $validated['background_color'],
            'text_color' => (string) $validated['text_color'],
            'heading_font' => (string) $validated['heading_font'],
            'body_font' => (string) $validated['body_font'],
            'logo_media_asset_id' => isset($validated['logo_media_asset_id']) ? (int) $validated['logo_media_asset_id'] : null,
            'secondary_logo_media_asset_id' => isset($validated['secondary_logo_media_asset_id'])
                ? (int) $validated['secondary_logo_media_asset_id']
                : null,
        ];
    }
}
