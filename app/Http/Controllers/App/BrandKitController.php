<?php

namespace App\Http\Controllers\App;

use App\Actions\BrandKit\UpdateBrandKit;
use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\BrandKit\UpdateBrandKitRequest;
use App\Models\BrandKit;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrandKitController extends Controller
{
    public function show(Request $request): Response
    {
        [$user, $workspace] = $this->context($request);
        $role = $user->roleIn($workspace);
        abort_unless($role?->canViewBrandKit() ?? false, 403);

        $brandKit = BrandKit::query()->firstOrCreate(
            ['workspace_id' => $workspace->id],
            BrandKit::defaultsFor($workspace),
        );

        $brandKit->load(['logo', 'secondaryLogo']);

        $mediaOptions = MediaAsset::query()
            ->forWorkspace($workspace)
            ->whereIn('type', [MediaType::Image->value, MediaType::Logo->value])
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->map(fn (MediaAsset $asset) => [
                'id' => $asset->id,
                'name' => $asset->name,
                'type' => $asset->type->value,
                'preview_url' => $asset->publicUrl(),
            ])
            ->values();

        return Inertia::render('app/brand-kit/index', [
            'brandKit' => $this->payload($brandKit),
            'mediaOptions' => $mediaOptions,
            'fonts' => array_map(
                fn (string $font) => ['value' => $font, 'label' => $font],
                BrandKit::allowedFonts(),
            ),
            'permissions' => [
                'can_manage' => $role->canManageBrandKit(),
            ],
        ]);
    }

    public function update(
        UpdateBrandKitRequest $request,
        BrandKit $brandKit,
        UpdateBrandKit $action,
    ): RedirectResponse {
        $action->handle($request->user(), $brandKit, $request->brandKitData());

        return back()->with('success', 'Brand Kit saved.');
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function context(Request $request): array
    {
        $user = $request->user();
        $workspace = $user?->currentWorkspace;

        abort_unless($user && $workspace && $user->belongsToWorkspace($workspace), 403);

        return [$user, $workspace];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(BrandKit $brandKit): array
    {
        return [
            'id' => $brandKit->id,
            'name' => $brandKit->name,
            'tagline' => $brandKit->tagline,
            'primary_color' => $brandKit->primary_color,
            'secondary_color' => $brandKit->secondary_color,
            'accent_color' => $brandKit->accent_color,
            'background_color' => $brandKit->background_color,
            'text_color' => $brandKit->text_color,
            'heading_font' => $brandKit->heading_font,
            'body_font' => $brandKit->body_font,
            'logo_media_asset_id' => $brandKit->logo_media_asset_id,
            'secondary_logo_media_asset_id' => $brandKit->secondary_logo_media_asset_id,
            'logo_url' => $brandKit->logo?->publicUrl(),
            'secondary_logo_url' => $brandKit->secondaryLogo?->publicUrl(),
        ];
    }
}
