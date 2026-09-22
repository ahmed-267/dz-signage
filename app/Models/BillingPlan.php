<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Persisted commercial plan (Starter / Business / Enterprise).
 * Pro is intentionally absent — never seed or expose it.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property string $currency
 * @property int|null $monthly_amount
 * @property int|null $annual_amount
 * @property int|null $yearly_monthly_equivalent
 * @property string|null $monthly_stripe_price_id
 * @property string|null $annual_stripe_price_id
 * @property string|null $stripe_product_id
 * @property list<string|array{price_id: string, interval: string}>|null $legacy_stripe_price_ids
 * @property int|null $screen_limit
 * @property int|null $storage_gb
 * @property int|null $team_member_limit
 * @property array<int, string>|null $features
 * @property array<int, string>|null $feature_labels
 * @property string|null $badge
 * @property bool $popular
 * @property bool $active
 * @property bool $public
 * @property int $sort_order
 * @property bool $enterprise
 * @property string $cta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BillingPlan extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'currency',
        'monthly_amount',
        'annual_amount',
        'yearly_monthly_equivalent',
        'monthly_stripe_price_id',
        'annual_stripe_price_id',
        'stripe_product_id',
        'legacy_stripe_price_ids',
        'screen_limit',
        'storage_gb',
        'team_member_limit',
        'features',
        'feature_labels',
        'badge',
        'popular',
        'active',
        'public',
        'sort_order',
        'enterprise',
        'cta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_amount' => 'integer',
            'annual_amount' => 'integer',
            'yearly_monthly_equivalent' => 'integer',
            'screen_limit' => 'integer',
            'storage_gb' => 'integer',
            'team_member_limit' => 'integer',
            'features' => 'array',
            'feature_labels' => 'array',
            'legacy_stripe_price_ids' => 'array',
            'popular' => 'boolean',
            'active' => 'boolean',
            'public' => 'boolean',
            'sort_order' => 'integer',
            'enterprise' => 'boolean',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('public', true);
    }

    public function stripeSynced(): bool
    {
        if ($this->enterprise) {
            return true;
        }

        return filled($this->monthly_stripe_price_id) || filled($this->annual_stripe_price_id);
    }

    /**
     * Catalog-compatible array (matches BillingPlanCatalog::normalize shape).
     *
     * @return array<string, mixed>
     */
    public function toCatalogArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'tagline' => (string) ($this->description ?? ''),
            'monthly_amount' => $this->monthly_amount,
            'yearly_amount' => $this->annual_amount,
            'yearly_monthly_equivalent' => $this->yearly_monthly_equivalent,
            'prices' => [
                'monthly' => $this->monthly_stripe_price_id,
                'yearly' => $this->annual_stripe_price_id,
            ],
            'stripe_product_id' => $this->stripe_product_id,
            'screen_limit' => $this->screen_limit,
            'storage_gb' => $this->storage_gb,
            'team_limit' => $this->team_member_limit,
            'features' => array_values($this->features ?? []),
            'feature_labels' => array_values($this->feature_labels ?? []),
            'badge' => $this->badge,
            'popular' => $this->popular,
            'enterprise' => $this->enterprise,
            'active' => $this->active,
            'public' => $this->public,
            'sort_order' => $this->sort_order,
            'cta' => $this->cta,
            'currency' => strtoupper($this->currency),
            'stripe_synced' => $this->stripeSynced(),
        ];
    }
}
