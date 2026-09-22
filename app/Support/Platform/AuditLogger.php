<?php

namespace App\Support\Platform;

use App\Models\AuditLog;
use App\Models\User;
use Throwable;

final class AuditLogger
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'device_token',
        'secret',
        'api_key',
        'authorization',
        'current_password',
        'recovery_codes',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function record(
        ?User $actor,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?int $workspaceId = null,
        array $metadata = [],
    ): void {
        try {
            AuditLog::query()->create([
                'actor_user_id' => $actor?->id,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'workspace_id' => $workspaceId,
                'metadata' => self::sanitize($metadata) ?: null,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Audit must never break the primary operation.
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private static function sanitize(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            $normalized = strtolower((string) $key);

            foreach (self::SENSITIVE_KEYS as $sensitive) {
                if (str_contains($normalized, $sensitive)) {
                    continue 2;
                }
            }

            if (is_array($value)) {
                $clean[$key] = self::sanitize($value);

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
