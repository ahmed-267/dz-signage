<?php

namespace App\Actions\Pairing;

use App\Enums\ScreenOperationalStatus;
use App\Models\PairingSession;
use App\Models\Screen;
use App\Models\ScreenDevice;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Billing\BillingEntitlement;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClaimPairingSession
{
    /**
     * @return array{screen: Screen, device: ScreenDevice, device_token: string}
     */
    public function handle(
        User $user,
        Workspace $workspace,
        string $name,
        ?string $code = null,
        ?string $publicId = null,
        ?string $orientation = null,
        ?int $locationId = null,
    ): array {
        if (($code === null || trim($code) === '') && ($publicId === null || trim($publicId) === '')) {
            throw ValidationException::withMessages([
                'code' => 'A pairing code or session id is required.',
            ]);
        }

        if (! $user->belongsToWorkspace($workspace)) {
            throw ValidationException::withMessages([
                'workspace' => 'You are not a member of this workspace.',
            ]);
        }

        if (! ($user->roleIn($workspace)?->canManageScreens() ?? false)) {
            throw ValidationException::withMessages([
                'screen' => 'You are not allowed to pair screens in this workspace.',
            ]);
        }

        return DB::transaction(function () use ($user, $workspace, $name, $code, $publicId, $orientation, $locationId) {
            $session = $this->findClaimableSession($code, $publicId);

            if ($session === null) {
                Log::info('pairing.claim_failed', [
                    'public_id' => $publicId,
                    'workspace_id' => $workspace->id,
                    'reason' => 'not_claimable',
                ]);

                throw ValidationException::withMessages([
                    'code' => 'This pairing code is invalid, expired, or already used.',
                ]);
            }

            $session = PairingSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->first();

            if ($session === null || ! $session->isClaimable()) {
                throw ValidationException::withMessages([
                    'code' => 'This pairing code is invalid, expired, or already used.',
                ]);
            }

            if ($code !== null && trim($code) !== '' && ! $session->verifyCode($code)) {
                throw ValidationException::withMessages([
                    'code' => 'This pairing code is invalid, expired, or already used.',
                ]);
            }

            // Screen licences are checked under a workspace lock before the
            // Screen exists, so a concurrent claim cannot overshoot the
            // purchased quantity. The session stays unclaimed when this throws.
            BillingEntitlement::assertCanPairLocked($workspace);

            $deviceToken = Str::random(64);
            $deviceIdentifier = 'dev_'.Str::lower((string) Str::ulid());

            $screen = Screen::query()->create([
                'workspace_id' => $workspace->id,
                'name' => trim($name) !== '' ? trim($name) : 'Untitled Screen',
                'location_id' => $locationId,
                'orientation' => $orientation,
                'operational_status' => ScreenOperationalStatus::Active,
                'created_by' => $user->id,
            ]);

            $device = ScreenDevice::query()->create([
                'screen_id' => $screen->id,
                'device_identifier' => $deviceIdentifier,
                'device_token_hash' => ScreenDevice::hashToken($deviceToken),
                'device_name' => null,
                'platform_meta' => $session->device_meta,
                'paired_at' => now(),
                'revoked_at' => null,
                'last_seen_at' => null,
            ]);

            $session->forceFill([
                'claimed_at' => now(),
                'claimed_by' => $user->id,
                'screen_id' => $screen->id,
                'pending_device_token_ciphertext' => Crypt::encryptString($deviceToken),
            ])->save();

            Log::info('pairing.session_claimed', [
                'public_id' => $session->public_id,
                'workspace_id' => $workspace->id,
                'screen_id' => $screen->id,
            ]);

            return [
                'screen' => $screen,
                'device' => $device,
                'device_token' => $deviceToken,
            ];
        });
    }

    private function findClaimableSession(?string $code, ?string $publicId): ?PairingSession
    {
        $query = PairingSession::query()
            ->whereNull('claimed_at')
            ->where('expires_at', '>', now());

        if ($publicId !== null && trim($publicId) !== '') {
            return (clone $query)->where('public_id', trim($publicId))->first();
        }

        return $query
            ->where('code_hash', PairingSession::hashCode((string) $code))
            ->first();
    }
}
