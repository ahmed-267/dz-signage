<?php

namespace App\Http\Controllers\App;

use App\Enums\SupportRequestCategory;
use App\Enums\SupportRequestPriority;
use App\Enums\SupportRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportRequest;
use App\Support\Platform\AuditLogger;
use App\Support\Platform\PlatformSettingsStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HelpController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user?->currentWorkspace;

        abort_unless($user && $workspace, 403);

        $recent = SupportRequest::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (SupportRequest $r) => [
                'id' => $r->id,
                'subject' => $r->subject,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'priority' => $r->priority->value,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return Inertia::render('app/help/index', [
            'support_email' => PlatformSettingsStore::effective('support_email'),
            'categories' => array_map(
                fn (SupportRequestCategory $c) => ['value' => $c->value, 'label' => $c->label()],
                SupportRequestCategory::cases(),
            ),
            'priorities' => array_map(
                fn (SupportRequestPriority $p) => ['value' => $p->value, 'label' => $p->label()],
                SupportRequestPriority::cases(),
            ),
            'recent_requests' => $recent,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user?->currentWorkspace;

        abort_unless($user && $workspace, 403);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', Rule::in(SupportRequestCategory::values())],
            'message' => ['required', 'string', 'max:5000'],
            'priority' => ['sometimes', 'string', Rule::in(SupportRequestPriority::values())],
        ]);

        $support = SupportRequest::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'subject' => $data['subject'],
            'category' => $data['category'],
            'message' => $data['message'],
            'priority' => $data['priority'] ?? SupportRequestPriority::Medium->value,
            'status' => SupportRequestStatus::Open->value,
        ]);

        AuditLogger::record(
            $user,
            'support_request.created',
            'support_request',
            $support->id,
            $workspace->id,
            [
                'subject' => $support->subject,
                'category' => $support->category->value,
            ],
        );

        return back()->with('success', 'Support request submitted.');
    }
}
