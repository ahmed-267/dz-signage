<?php

namespace App\Models;

use App\Enums\WorkspaceIndustry;
use App\Enums\WorkspaceRole;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;

/**
 * The Workspace is the Stripe customer — never the User. Screen licences are
 * bought per Workspace and every subscription/invoice row is Workspace scoped.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property WorkspaceIndustry $industry
 * @property string $country
 * @property string $timezone
 * @property string|null $logo_path
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $members_count
 * @property-read int|null $screens_count
 * @property-read int|null $active_screens_count
 * @property-read int|null $online_screens_count
 */
class Workspace extends Model
{
    use Billable;

    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    use SoftDeletes;

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
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * Stripe receives the Workspace Owner's email so receipts reach the payer.
     */
    public function stripeEmail(): ?string
    {
        $this->loadMissing('ownerMembership.user');

        return $this->ownerMembership?->user?->email;
    }

    public function stripeName(): ?string
    {
        return $this->name;
    }

    /**
     * @return array<string, string>|null
     */
    public function stripeMetadata(): ?array
    {
        return [
            'workspace_id' => (string) $this->id,
            'workspace_name' => (string) $this->name,
            'workspace_slug' => (string) $this->slug,
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
     * @return HasMany<MediaAsset, $this>
     */
    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    /**
     * @return HasOne<BrandKit, $this>
     */
    public function brandKit(): HasOne
    {
        return $this->hasOne(BrandKit::class);
    }

    /**
     * @return HasOne<WorkspaceBillingOverride, $this>
     */
    public function billingOverride(): HasOne
    {
        return $this->hasOne(WorkspaceBillingOverride::class);
    }

    /**
     * @return HasMany<ScreenDesign, $this>
     */
    public function screenDesigns(): HasMany
    {
        return $this->hasMany(ScreenDesign::class);
    }

    /**
     * @return HasMany<Playlist, $this>
     */
    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }

    /**
     * @return HasMany<Schedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * @return HasMany<Screen, $this>
     */
    public function screens(): HasMany
    {
        return $this->hasMany(Screen::class);
    }

    /**
     * @return HasMany<Deployment, $this>
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    /**
     * @return HasMany<Template, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    /**
     * @return HasMany<BillingInvoice, $this>
     */
    public function billingInvoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class);
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
