<?php

namespace App\Actions\Workspaces;

use App\Models\ScreenDevice;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Billing\BillingEntitlement;
use App\Support\Platform\AuditLogger;
use App\Support\Platform\PlatformPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Recoverable Business removal. Rows stay in the database (soft delete).
 * Media files are not purged. An active Stripe subscription is cancelled
 * immediately so a removed Business is not left on a billable plan.
 */
class SoftDeleteWorkspace
{
    public function handle(User $actor, Workspace $workspace, string $confirmName): void
    {
        if (! PlatformPermissions::canDeleteWorkspace($actor)) {
            abort(403);
        }

        if ($confirmName !== $workspace->name) {
            throw ValidationException::withMessages([
                'confirm_name' => 'Type the Business name exactly to confirm deletion.',
            ]);
        }

        DB::transaction(function () use ($actor, $workspace): void {
            $subscription = BillingEntitlement::subscription($workspace);

            if ($subscription !== null && ! $subscription->canceled() && ! $subscription->ended()) {
                try {
                    $subscription->cancelNow();
                } catch (Throwable $e) {
                    report($e);

                    throw ValidationException::withMessages([
                        'confirm_name' => 'Stripe could not cancel this Business subscription, so it was not deleted.',
                    ]);
                }
            }

            ScreenDevice::query()
                ->whereHas('screen', fn ($query) => $query->where('workspace_id', $workspace->id))
                ->whereNull('revoked_at')
                ->each(function (ScreenDevice $device): void {
                    $device->forceFill([
                        'revoked_at' => now(),
                        'device_token_hash' => ScreenDevice::hashToken(bin2hex(random_bytes(32))),
                    ])->save();
                });

            User::query()
                ->where('current_workspace_id', $workspace->id)
                ->update(['current_workspace_id' => null]);

            AuditLogger::record(
                $actor,
                'workspace.soft_deleted',
                'workspace',
                $workspace->id,
                $workspace->id,
                [
                    'name' => $workspace->name,
                    'subscription_canceled' => $subscription !== null,
                ],
            );

            $workspace->delete();
        });
    }
}
