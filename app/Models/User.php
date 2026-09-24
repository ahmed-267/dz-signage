<?php

namespace App\Models;

use App\Enums\PlatformRole;
use App\Enums\WorkspaceRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_admin
 * @property PlatformRole|null $platform_role
 * @property int|null $current_workspace_id
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $onboarding_started_at
 * @property Carbon|null $onboarding_completed_at
 * @property Carbon|null $onboarding_skipped_at
 * @property int|null $onboarding_step
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Platform Super Admin (legacy flag kept in sync with platform_role).
     */
    public function isAdmin(): bool
    {
        return $this->isSuperAdmin();
    }

    public function isSuperAdmin(): bool
    {
        if ($this->platform_role === PlatformRole::SuperAdmin) {
            return true;
        }

        return (bool) $this->is_admin;
    }

    public function isPlatformAdmin(): bool
    {
        return $this->platform_role === PlatformRole::PlatformAdmin;
    }

    public function isPlatformStaff(): bool
    {
        return $this->platformRole() !== null;
    }

    public function platformRole(): ?PlatformRole
    {
        if ($this->platform_role instanceof PlatformRole) {
            return $this->platform_role;
        }

        if ((bool) $this->is_admin) {
            return PlatformRole::SuperAdmin;
        }

        return null;
    }

    public function canManagePlatformTemplates(): bool
    {
        return $this->platformRole()?->canManagePlatformTemplates() ?? false;
    }

    /**
     * Assign a platform role and keep legacy is_admin in sync for Super Admin.
     */
    public function assignPlatformRole(?PlatformRole $role): void
    {
        $this->forceFill([
            'platform_role' => $role,
            'is_admin' => $role === PlatformRole::SuperAdmin,
        ])->save();
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    /**
     * @return HasMany<WorkspaceMember, $this>
     */
    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * @return BelongsToMany<Workspace, $this>
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot(['id', 'role'])
            ->withTimestamps();
    }

    /**
     * Locations this user manages as a Location Manager.
     *
     * @return BelongsToMany<Location, $this>
     */
    public function managedLocations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_user')
            ->withTimestamps();
    }

    public function belongsToWorkspace(Workspace|int $workspace): bool
    {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return $this->workspaceMemberships()
            ->where('workspace_id', $workspaceId)
            ->exists();
    }

    public function membershipFor(Workspace|int $workspace): ?WorkspaceMember
    {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return $this->workspaceMemberships()
            ->where('workspace_id', $workspaceId)
            ->first();
    }

    public function roleIn(Workspace|int $workspace): ?WorkspaceRole
    {
        return $this->membershipFor($workspace)?->role;
    }

    public function hasWorkspaceMemberships(): bool
    {
        return $this->workspaceMemberships()->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_admin' => 'boolean',
            'platform_role' => PlatformRole::class,
            'onboarding_started_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'onboarding_skipped_at' => 'datetime',
        ];
    }
}
