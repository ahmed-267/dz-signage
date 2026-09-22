<?php

namespace App\Models;

use App\Enums\MediaType;
use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $workspace_id
 * @property MediaType $type
 * @property string $name
 * @property string|null $original_filename
 * @property string|null $storage_disk
 * @property string|null $storage_path
 * @property string|null $mime_type
 * @property string|null $extension
 * @property int|null $size_bytes
 * @property int|null $width
 * @property int|null $height
 * @property int|null $duration_seconds
 * @property string|null $text_content
 * @property string|null $url
 * @property array<string, mixed>|null $metadata
 * @property int $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MediaAsset extends Model
{
    /** @use HasFactory<MediaAssetFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'type',
        'name',
        'original_filename',
        'storage_disk',
        'storage_path',
        'mime_type',
        'extension',
        'size_bytes',
        'width',
        'height',
        'duration_seconds',
        'text_content',
        'url',
        'metadata',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'metadata' => 'array',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_seconds' => 'integer',
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

    /**
     * @param  Builder<MediaAsset>  $query
     * @return Builder<MediaAsset>
     */
    public function scopeForWorkspace(Builder $query, Workspace|int $workspace): Builder
    {
        $id = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return $query->where('workspace_id', $id);
    }

    public function hasStoredFile(): bool
    {
        return filled($this->storage_disk) && filled($this->storage_path);
    }

    public function publicUrl(): ?string
    {
        if (! $this->hasStoredFile()) {
            return null;
        }

        return Storage::disk($this->storage_disk)->url($this->storage_path);
    }

    public function dimensionsLabel(): ?string
    {
        if ($this->width && $this->height) {
            return $this->width.'×'.$this->height;
        }

        return null;
    }

    /**
     * True when this MediaAsset is referenced by one or more Screen Designs.
     */
    public function hasDependencies(): bool
    {
        return $this->referencingScreenDesignsCount() > 0;
    }

    public function referencingScreenDesignsCount(): int
    {
        $needle = '"mediaAssetId":'.$this->id;
        $needleSpaced = '"mediaAssetId": '.$this->id;

        return ScreenDesign::query()
            ->where('workspace_id', $this->workspace_id)
            ->whereHas('versions', function ($query) use ($needle, $needleSpaced): void {
                $query->where(function ($inner) use ($needle, $needleSpaced): void {
                    $inner->whereRaw('schema::text LIKE ?', ['%'.$needle.'%'])
                        ->orWhereRaw('schema::text LIKE ?', ['%'.$needleSpaced.'%']);
                });
            })
            ->count();
    }
}
