<?php

namespace App\Policies;

use App\Enums\TemplateStatus;
use App\Models\Template;
use App\Models\User;

class TemplatePolicy
{
    /**
     * Customer library: any workspace member may browse published platform templates.
     * Platform staff may list all platform templates via admin.
     */
    public function viewAny(User $user): bool
    {
        if ($user->canManagePlatformTemplates()) {
            return true;
        }

        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && ($user->roleIn($workspace)?->canViewTemplates() ?? false);
    }

    public function view(User $user, Template $template): bool
    {
        if (! $template->isPlatform()) {
            return false;
        }

        if ($user->canManagePlatformTemplates()) {
            return true;
        }

        if ($template->status !== TemplateStatus::Published) {
            return false;
        }

        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && ($user->roleIn($workspace)?->canViewTemplates() ?? false);
    }

    /**
     * Customers never create templates.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function createPlatform(User $user): bool
    {
        return $user->canManagePlatformTemplates();
    }

    public function update(User $user, Template $template): bool
    {
        return $template->isPlatform() && $user->canManagePlatformTemplates();
    }

    public function delete(User $user, Template $template): bool
    {
        return $this->update($user, $template);
    }

    public function publish(User $user, Template $template): bool
    {
        return $this->update($user, $template);
    }

    public function archive(User $user, Template $template): bool
    {
        return $this->update($user, $template);
    }

    public function duplicate(User $user, Template $template): bool
    {
        return false;
    }

    public function favourite(User $user, Template $template): bool
    {
        return $this->view($user, $template);
    }

    /**
     * Use Template → create independent Screen Design (Phase 4).
     */
    public function useTemplate(User $user, Template $template): bool
    {
        if (! $this->view($user, $template)) {
            return false;
        }

        if ($template->published_version_id === null) {
            return false;
        }

        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && ($user->roleIn($workspace)?->canManageScreenDesigns() ?? false);
    }
}
