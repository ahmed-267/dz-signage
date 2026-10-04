<?php

namespace App\Actions\Pairing;

use App\Models\PairingSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreatePairingSession
{
    private const CODE_CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 4;

    private const TTL_MINUTES = 10;

    /**
     * @param  array<string, mixed>|null  $deviceMeta
     * @return array{session: PairingSession, code: string}
     */
    public function handle(?Request $request = null, ?array $deviceMeta = null): array
    {
        $publicId = (string) Str::ulid();
        $code = $this->generateUniqueCode();

        $session = PairingSession::query()->create([
            'public_id' => $publicId,
            'code_hash' => PairingSession::hashCode($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'device_meta' => $deviceMeta,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);

        Log::info('pairing.session_created', [
            'public_id' => $session->public_id,
        ]);

        return [
            'session' => $session,
            'code' => $code,
        ];
    }

    private function generateUniqueCode(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $suffix = '';
            $charsetLength = strlen(self::CODE_CHARSET);
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $suffix .= self::CODE_CHARSET[random_int(0, $charsetLength - 1)];
            }
            $code = 'DZ-'.$suffix;
            $hash = PairingSession::hashCode($code);

            $exists = PairingSession::query()
                ->where('code_hash', $hash)
                ->whereNull('claimed_at')
                ->where('expires_at', '>', now())
                ->exists();

            if (! $exists) {
                return $code;
            }
        }

        return 'DZ-'.Str::upper(Str::random(self::CODE_LENGTH));
    }
}
