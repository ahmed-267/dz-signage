<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Workspace-owned physical site that groups Screens.
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $region
 * @property string|null $postcode
 * @property string $country
 * @property string $timezone
 * @property string|null $notes
 * @property Carbon|null $archived_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $screens_count
 */
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'address_line1',
        'address_line2',
        'city',
        'region',
        'postcode',
        'country',
        'timezone',
        'notes',
        'archived_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
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
     * @return HasMany<Screen, $this>
     */
    public function screens(): HasMany
    {
        return $this->hasMany(Screen::class);
    }

    /**
     * Location Managers assigned to this site.
     *
     * @return BelongsToMany<User, $this>
     */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'location_user')
            ->withTimestamps();
    }

    /**
     * @param  Builder<Location>  $query
     * @return Builder<Location>
     */
    public function scopeForWorkspace(Builder $query, Workspace|int $workspace): Builder
    {
        $id = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return $query->where('workspace_id', $id);
    }

    /**
     * @param  Builder<Location>  $query
     * @return Builder<Location>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<Location>  $query
     * @return Builder<Location>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function formattedAddress(): ?string
    {
        $parts = array_values(array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->region,
            $this->postcode,
            $this->country !== '' ? $this->country : null,
        ], fn (?string $part): bool => $part !== null && trim($part) !== ''));

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }
}
