<?php

namespace App\Http\Controllers\Player;

use App\Actions\Pairing\CreatePairingSession;
use App\Http\Controllers\Controller;
use App\Models\PairingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PairingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('player/index');
    }

    public function store(Request $request, CreatePairingSession $action): JsonResponse
    {
        $validated = $request->validate([
            'device_meta' => ['sometimes', 'nullable', 'array'],
            'device_meta.user_agent' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'device_meta.platform' => ['sometimes', 'nullable', 'string', 'max:128'],
            'device_meta.language' => ['sometimes', 'nullable', 'string', 'max:32'],
            'device_meta.form_factor' => ['sometimes', 'nullable', 'string', 'max:32'],
            'device_meta.player_version' => ['sometimes', 'nullable', 'string', 'max:32'],
            'device_meta.viewport' => ['sometimes', 'nullable', 'array'],
            'device_meta.viewport.width' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:16000'],
            'device_meta.viewport.height' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:16000'],
        ]);

        $result = $action->handle(
            $request,
            is_array($validated['device_meta'] ?? null) ? $validated['device_meta'] : null,
        );
        /** @var PairingSession $session */
        $session = $result['session'];
        $code = $result['code'];

        return response()->json([
            'public_id' => $session->public_id,
            'code' => $code,
            'expires_at' => $session->expires_at->toIso8601String(),
            'expires_in_seconds' => $this->expiresInSeconds($session),
            'pair_url' => url('/app/screens/pair/'.$session->public_id),
        ], 201);
    }

    public function show(string $publicId): JsonResponse
    {
        $session = PairingSession::query()
            ->where('public_id', $publicId)
            ->first();

        if ($session === null) {
            return response()->json([
                'status' => 'expired',
            ]);
        }

        if ($session->isClaimed()) {
            $payload = [
                'status' => 'claimed',
                'screen_id' => $session->screen_id,
            ];

            if (filled($session->pending_device_token_ciphertext)) {
                try {
                    $token = Crypt::decryptString($session->pending_device_token_ciphertext);
                    $payload['device_token'] = $token;
                } catch (Throwable) {
                    // Ciphertext unreadable — treat as already consumed.
                }

                $session->forceFill([
                    'pending_device_token_ciphertext' => null,
                ])->save();
            }

            return response()->json($payload);
        }

        if ($session->isExpired()) {
            return response()->json([
                'status' => 'expired',
            ]);
        }

        return response()->json([
            'status' => 'pending',
            'expires_at' => $session->expires_at->toIso8601String(),
            'expires_in_seconds' => $this->expiresInSeconds($session),
        ]);
    }

    private function expiresInSeconds(PairingSession $session): int
    {
        if ($session->expires_at->lte(now())) {
            return 0;
        }

        return (int) now()->diffInSeconds($session->expires_at);
    }
}
