<?php

namespace App\Models;

use App\Enums\ScreenDesignStatus;
use App\Enums\TemplateOrientation;
use Database\Factories\ScreenDesignFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property TemplateOrientation $orientation
 * @property int $canvas_width
 * @property int $canvas_height
 * @property int|null $source_template_id
 * @property int|null $source_template_version_id
 * @property ScreenDesignStatus $status
 * @property string|null $thumbnail_path
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $published_version_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ScreenDesign extends Model
{
    /** @use HasFactory<ScreenDesignFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'orientation',
        'canvas_width',
        'canvas_height',
        'source_template_id',
        'source_template_version_id',
        'status',
        'thumbnail_path',
        'created_by',
        'updated_by',
        'published_version_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orientation' => TemplateOrientation::class,
            'status' => ScreenDesignStatus::class,
            'canvas_width' => 'integer',
            'canvas_height' => 'integer',
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
     * @return HasMany<ScreenDesignVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ScreenDesignVersion::class);
    }

    /**
     * @return BelongsTo<ScreenDesignVersion, $this>
     */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(ScreenDesignVersion::class, 'published_version_id');
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function sourceTemplate(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'source_template_id');
    }

    /**
     * @return BelongsTo<TemplateVersion, $this>
     */
    public function sourceTemplateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class, 'source_template_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Deployment, $this>
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    /**
     * @param  Builder<ScreenDesign>  $query
     * @return Builder<ScreenDesign>
     */
    public function scopeForWorkspace(Builder $query, Workspace|int $workspace): Builder
    {
        $id = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return $query->where('workspace_id', $id);
    }

    public function latestVersion(): ?ScreenDesignVersion
    {
        return $this->versions()->orderByDesc('version_number')->first();
    }

    /**
     * Collect media asset IDs referenced in the latest (or published) schema.
     *
     * @return list<int>
     */
    public function referencedMediaAssetIds(): array
    {
        $version = $this->latestVersion() ?? $this->publishedVersion;
        $schema = $version?->schema;
        if (! is_array($schema)) {
            return [];
        }

        return self::mediaIdsFromSchema($schema);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<int>
     */
    public static function mediaIdsFromSchema(array $schema): array
    {
        $ids = [];

        $background = $schema['canvas']['background'] ?? null;
        if (is_array($background) && isset($background['mediaAssetId'])) {
            $ids[] = (int) $background['mediaAssetId'];
        }

        foreach ($schema['elements'] ?? [] as $element) {
            if (! is_array($element)) {
                continue;
            }
            $props = $element['props'] ?? [];
            if (! is_array($props)) {
                continue;
            }
            if (isset($props['mediaAssetId'])) {
                $ids[] = (int) $props['mediaAssetId'];
            }

            $config = $props['config'] ?? null;
            if (is_array($config) && isset($config['mediaAssetId'])) {
                $ids[] = (int) $config['mediaAssetId'];
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
