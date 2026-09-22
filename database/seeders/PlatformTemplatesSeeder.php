<?php

namespace Database\Seeders;

use App\Enums\TemplateStatus;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Support\Rendering\StarterTemplateCatalog;
use Illuminate\Database\Seeder;

/**
 * Seeds published DZ platform templates from the starter catalog.
 *
 * Run: php artisan db:seed --class=PlatformTemplatesSeeder
 */
class PlatformTemplatesSeeder extends Seeder
{
    /**
     * Legacy platform template names from the previous seeder (pre-slug catalog).
     *
     * @var list<string>
     */
    private const OLD_PLATFORM_TEMPLATE_NAMES = [
        'Masjid Prayer Times',
        'Jumuah Announcement',
        'Ramadan Timetable',
        'Lecture Night',
        'Community Notice',
        'Fundraising Appeal',
        'Quran Class',
        'Retail Promo',
        'Corporate Welcome',
        'Restaurant Menu Board',
        'Education Timetable',
        'Healthcare Waiting Room',
        'Hotel Concierge',
        'Gym Class Board',
        'Real Estate Feature',
        'Event Welcome',
    ];

    public function run(): void
    {
        $admin = User::query()->where('platform_role', 'super_admin')->orWhere('is_admin', true)->first()
            ?? User::factory()->admin()->create(['email' => 'platform-templates@dz.local']);

        $definitions = StarterTemplateCatalog::definitions();
        $catalogSlugs = [];

        foreach ($definitions as $definition) {
            Template::query()
                ->whereNull('workspace_id')
                ->where('name', $definition['name'])
                ->whereNull('slug')
                ->update(['slug' => $definition['slug']]);
        }

        foreach ($definitions as $definition) {
            $orientation = $definition['orientation'];
            $theme = $definition['theme'];
            $slug = $definition['slug'];

            $template = Template::query()->updateOrCreate(
                [
                    'workspace_id' => null,
                    'slug' => $slug,
                ],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'category' => $definition['category'],
                    'industry' => $definition['industry'],
                    'orientation' => $orientation,
                    'canvas_width' => $orientation->canvasWidth(),
                    'canvas_height' => $orientation->canvasHeight(),
                    'theme' => $theme,
                    'status' => TemplateStatus::Published,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );

            $version = TemplateVersion::query()->updateOrCreate(
                [
                    'template_id' => $template->id,
                    'version_number' => 1,
                ],
                [
                    'schema' => $definition['schema'],
                    'created_by' => $admin->id,
                    'published_at' => now(),
                ],
            );

            $template->forceFill([
                'published_version_id' => $version->id,
                'status' => TemplateStatus::Published,
            ])->save();

            $catalogSlugs[] = $slug;
        }

        Template::query()
            ->whereNull('workspace_id')
            ->whereIn('name', self::OLD_PLATFORM_TEMPLATE_NAMES)
            ->where(function ($query) use ($catalogSlugs): void {
                $query->whereNull('slug')
                    ->orWhereNotIn('slug', $catalogSlugs);
            })
            ->update(['status' => TemplateStatus::Archived]);
    }
}
