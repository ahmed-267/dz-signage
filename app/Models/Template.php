<?php

namespace App\Models;

use App\Enums\TemplateCategory;
use App\Enums\TemplateOrientation;
use App\Enums\TemplateStatus;
use App\Enums\TemplateTheme;
use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $workspace_id
 * @property string $name
 * @property string|null $slug
 * @property string|null $description
 * @property TemplateCategory $category
 * @property string|null $industry
 * @property TemplateOrientation $orientation
 * @property int $canvas_width
 * @property int $canvas_height
 * @property TemplateTheme $theme
 * @property TemplateStatus $status
 * @property string|null $thumbnail_path
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $published_version_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'slug',
        'description',
        'category',
        'industry',
        'orientation',
        'canvas_width',
        'canvas_height',
        'theme',
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
            'category' => TemplateCategory::class,
            'orientation' => TemplateOrientation::class,
            'theme' => TemplateTheme::class,
            'status' => TemplateStatus::class,
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
     * @return HasMany<TemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class);
    }

    /**
     * @return BelongsTo<TemplateVersion, $this>
     */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class, 'published_version_id');
    }

    /**
     * @return HasMany<TemplateFavourite, $this>
     */
    public function favourites(): HasMany
    {
        return $this->hasMany(TemplateFavourite::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isPlatform(): bool
    {
        return $this->workspace_id === null;
    }

    /**
     * @param  Builder<Template>  $query
     * @return Builder<Template>
     */
    public function scopePlatform(Builder $query): Builder
    {
        return $query->whereNull('workspace_id');
    }

    /**
     * @param  Builder<Template>  $query
     * @return Builder<Template>
     */
    public function scopeForWorkspace(Builder $query, Workspace|int $workspace): Builder
    {
        $id = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return $query->where('workspace_id', $id);
    }

    /**
     * @param  Builder<Template>  $query
     * @return Builder<Template>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', TemplateStatus::Published)
            ->whereNotNull('published_version_id');
    }

    public function latestVersion(): ?TemplateVersion
    {
        return $this->versions()->orderByDesc('version_number')->first();
    }

    /**
     * @return HasOne<TemplateVersion, $this>
     */
    public function latestVersionRecord(): HasOne
    {
        return $this->hasOne(TemplateVersion::class)->ofMany('version_number', 'max');
    }
}
