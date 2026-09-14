<?php

namespace App\Models;

use App\Enums\WorkspaceIndustry;
use App\Enums\WorkspaceRole;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property WorkspaceIndustry $industry
 * @property string $country
 * @property string $timezone
 * @property string|null $logo_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'industry',
        'country',
        'timezone',
        'logo_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'industry' => WorkspaceIndustry::class,
        ];
    }

    /**
     * @return HasMany<WorkspaceMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * @return HasMany<WorkspaceInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    /**
     * @return HasOne<WorkspaceMember, $this>
     */
    public function ownerMembership(): HasOne
    {
        return $this->hasOne(WorkspaceMember::class)
            ->where('role', WorkspaceRole::Owner->value);
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return asset('storage/'.$this->logo_path);
    }
}
