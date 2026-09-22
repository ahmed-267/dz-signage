<?php

namespace App\Models;

use Database\Factories\ScreenDesignVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $screen_design_id
 * @property int $version_number
 * @property array<string, mixed> $schema
 * @property int|null $created_by
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ScreenDesignVersion extends Model
{
    /** @use HasFactory<ScreenDesignVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'screen_design_id',
        'version_number',
        'schema',
        'created_by',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'version_number' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ScreenDesign, $this>
     */
    public function screenDesign(): BelongsTo
    {
        return $this->belongsTo(ScreenDesign::class);
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

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
