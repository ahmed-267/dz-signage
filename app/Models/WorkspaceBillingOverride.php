<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Optional enterprise / custom entitlement overrides for a Workspace.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int|null $screen_limit
 * @property int|null $storage_gb
 * @property int|null $team_limit
 * @property array<int, string>|null $features
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WorkspaceBillingOverride extends Model
{
    protected $fillable = [
        'workspace_id',
        'screen_limit',
        'storage_gb',
        'team_limit',
        'features',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'screen_limit' => 'integer',
            'storage_gb' => 'integer',
            'team_limit' => 'integer',
            'features' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
