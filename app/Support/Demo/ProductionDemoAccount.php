<?php

namespace App\Support\Demo;

use App\Models\Workspace;

/**
 * Identifiers for the dedicated production demo Business.
 * All demo mutations must resolve through this helper — never broad deletes.
 */
final class ProductionDemoAccount
{
    public static function email(): string
    {
        return (string) config('rmsignage_demo.email', 'demo@rmsignage.com');
    }

    public static function workspaceSlug(): string
    {
        return (string) config('rmsignage_demo.workspace_slug', 'rmsignage-demo-north-bean');
    }

    public static function workspaceName(): string
    {
        return (string) config('rmsignage_demo.workspace_name', 'North & Bean Café');
    }

    public static function seedTag(): string
    {
        return (string) config('rmsignage_demo.seed_tag', 'rmsignage-production-demo');
    }

    public static function findWorkspace(): ?Workspace
    {
        return Workspace::query()
            ->where('slug', self::workspaceSlug())
            ->first();
    }

    public static function isDemoWorkspace(Workspace $workspace): bool
    {
        return $workspace->slug === self::workspaceSlug();
    }
}
