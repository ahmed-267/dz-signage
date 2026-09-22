<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupportRequestCategory;
use App\Enums\SupportRequestPriority;
use App\Enums\SupportRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportRequest;
use App\Models\SupportRequestNote;
use App\Support\ListPagination;
use App\Support\Platform\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupportRequestController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isPlatformStaff(), 403);

        $q = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', 'all');
        $priority = (string) $request->input('priority', 'all');
        $category = (string) $request->input('category', 'all');
        $sort = ListPagination::sort(
            $request,
            ['subject', 'status', 'priority', 'updated'],
            'updated',
            'desc',
        );

        $query = SupportRequest::query()
            ->with(['workspace:id,name', 'user:id,name,email', 'assignee:id,name']);

        if ($q !== '') {
            $term = '%'.$q.'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('subject', 'ilike', $term)
                    ->orWhere('message', 'ilike', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'ilike', $term)->orWhere('name', 'ilike', $term))
                    ->orWhereHas('workspace', fn ($w) => $w->where('name', 'ilike', $term));
            });
        }

        if ($status !== 'all' && in_array($status, SupportRequestStatus::values(), true)) {
            $query->where('status', $status);
        }

        if ($priority !== 'all' && in_array($priority, SupportRequestPriority::values(), true)) {
            $query->where('priority', $priority);
        }

        if ($category !== 'all' && in_array($category, SupportRequestCategory::values(), true)) {
            $query->where('category', $category);
        }

        $direction = $sort['direction'];
        $query = match ($sort['column']) {
            'subject' => $query->orderBy('subject', $direction)->orderByDesc('id'),
            'status' => $query->orderBy('status', $direction)->orderByDesc('id'),
            'priority' => $query->orderBy('priority', $direction)->orderByDesc('id'),
            default => $query->orderBy('updated_at', $direction)->orderByDesc('id'),
        };

        $perPage = ListPagination::perPage($request, 20);

        $paginator = $query
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()
                ->map(fn (SupportRequest $r) => $this->listPayload($r))
                ->values(),
        );

        return Inertia::render('admin/support/index', [
            'requests' => ListPagination::inertia($paginator),
            'filters' => [
                'q' => $q,
                'status' => $status,
                'priority' => $priority,
                'category' => $category,
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
                'per_page' => $perPage,
            ],
            'statuses' => array_map(
                fn (SupportRequestStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                SupportRequestStatus::cases(),
            ),
            'priorities' => array_map(
                fn (SupportRequestPriority $p) => ['value' => $p->value, 'label' => $p->label()],
                SupportRequestPriority::cases(),
            ),
            'categories' => array_map(
                fn (SupportRequestCategory $c) => ['value' => $c->value, 'label' => $c->label()],
                SupportRequestCategory::cases(),
            ),
        ]);
    }

    public function show(SupportRequest $support): Response
    {
        abort_unless(request()->user()?->isPlatformStaff(), 403);

        $support->load([
            'workspace:id,name',
            'user:id,name,email',
            'assignee:id,name,email',
            'notes.user:id,name',
        ]);

        return Inertia::render('admin/support/show', [
            'request' => [
                ...$this->listPayload($support),
                'workspace_id' => $support->workspace_id,
                'message' => $support->message,
                'admin_notes' => $support->admin_notes,
                'assigned_to' => $support->assigned_to,
                'assignee_name' => $support->assignee?->name,
                'resolved_at' => $support->resolved_at?->toIso8601String(),
                'updated_at' => $support->updated_at?->toIso8601String(),
            ],
            'notes' => $support->notes->map(fn (SupportRequestNote $note) => [
                'id' => $note->id,
                'body' => $note->body,
                'is_internal' => $note->is_internal,
                'author_name' => $note->user?->name,
                'created_at' => $note->created_at?->toIso8601String(),
            ])->values(),
            'statuses' => array_map(
                fn (SupportRequestStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                SupportRequestStatus::cases(),
            ),
            'priorities' => array_map(
                fn (SupportRequestPriority $p) => ['value' => $p->value, 'label' => $p->label()],
                SupportRequestPriority::cases(),
            ),
            'can_update' => true,
        ]);
    }

    public function update(Request $request, SupportRequest $support): RedirectResponse
    {
        abort_unless($request->user()?->isPlatformStaff(), 403);

        $data = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(SupportRequestStatus::values())],
            'priority' => ['sometimes', 'string', Rule::in(SupportRequestPriority::values())],
            'admin_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $previousStatus = $support->status->value;
        $updates = [];

        if (array_key_exists('status', $data)) {
            $updates['status'] = $data['status'];
            $newStatus = SupportRequestStatus::from($data['status']);

            if (in_array($newStatus, [SupportRequestStatus::Resolved, SupportRequestStatus::Closed], true)) {
                $updates['resolved_at'] = $support->resolved_at ?? now();
            } elseif (in_array($newStatus, [SupportRequestStatus::Open, SupportRequestStatus::InProgress], true)) {
                $updates['resolved_at'] = null;
            }
        }

        if (array_key_exists('priority', $data)) {
            $updates['priority'] = $data['priority'];
        }

        if (array_key_exists('admin_notes', $data)) {
            $updates['admin_notes'] = $data['admin_notes'];
        }

        if (array_key_exists('assigned_to', $data)) {
            $updates['assigned_to'] = $data['assigned_to'];
        }

        if ($updates !== []) {
            $support->forceFill($updates)->save();
        }

        if (isset($data['status']) && $data['status'] !== $previousStatus) {
            AuditLogger::record(
                $request->user(),
                'support_request.status_updated',
                'support_request',
                $support->id,
                $support->workspace_id,
                [
                    'previous_status' => $previousStatus,
                    'new_status' => $data['status'],
                ],
            );
        }

        return back()->with('success', 'Support request updated.');
    }

    public function storeNote(Request $request, SupportRequest $support): RedirectResponse
    {
        abort_unless($request->user()?->isPlatformStaff(), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        SupportRequestNote::query()->create([
            'support_request_id' => $support->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'is_internal' => (bool) ($data['is_internal'] ?? true),
        ]);

        return back()->with('success', 'Note added.');
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(SupportRequest $request): array
    {
        return [
            'id' => $request->id,
            'subject' => $request->subject,
            'category' => $request->category->value,
            'category_label' => $request->category->label(),
            'priority' => $request->priority->value,
            'priority_label' => $request->priority->label(),
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'workspace_name' => $request->workspace?->name,
            'requester_name' => $request->user?->name,
            'requester_email' => $request->user?->email,
            'user_name' => $request->user?->name,
            'user_email' => $request->user?->email,
            'created_at' => $request->created_at?->toIso8601String(),
        ];
    }
}
