<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Task::class);

        $query = Task::query()
            ->with([
                'branch:id,name,code',
                'department:id,name,code',
                'project:id,name,code',
                'assignee.user:id,name',
                'creator.user:id,name',
            ]);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->query('project_id'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->query('department_id'));
        }

        if ($request->filled('assignee_id')) {
            $query->where('assignee_membership_id', $request->query('assignee_id'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->query('priority'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->boolean('archived')) {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->query('per_page', 30), 100);
        $tasks = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($tasks);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Task::class);

        $companyId = session('current_company_id');
        $membership = $request->user()->membershipIn($companyId);

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('tasks')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'status' => 'nullable|in:backlog,todo,in_progress,blocked,done,cancelled',
            'due_at' => 'nullable|date',
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'project_id' => [
                'nullable',
                Rule::exists('projects', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'assignee_membership_id' => [
                'nullable',
                Rule::exists('company_user', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
        ]);

        $task = Task::create([
            'id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'creator_membership_id' => $membership->id,
            'code' => strtoupper($validated['code']),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'] ?? 'normal',
            'status' => $validated['status'] ?? 'todo',
            'due_at' => $validated['due_at'] ?? null,
            'branch_id' => $validated['branch_id'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'project_id' => $validated['project_id'] ?? null,
            'assignee_membership_id' => $validated['assignee_membership_id'] ?? null,
        ]);

        if ($task->project_id) {
            $task->project?->recalculateProgress();
        }

        AuditEvent::log('task.created', 'task', $task->id, [
            'code' => $task->code,
            'title' => $task->title,
        ]);

        return response()->json([
            'message' => 'Task created successfully.',
            'task' => $task->load(['branch', 'department', 'project', 'assignee.user', 'creator.user']),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $task = Task::with([
            'branch',
            'department',
            'project',
            'assignee.user',
            'creator.user',
            'statusHistory.actor.user',
            'comments.author.user',
        ])->findOrFail($id);

        Gate::authorize('view', $task);

        return response()->json(['task' => $task]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $task = Task::findOrFail($id);
        Gate::authorize('update', $task);

        $companyId = session('current_company_id');

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:200',
            'description' => 'nullable|string',
            'priority' => 'sometimes|required|in:low,normal,high,urgent',
            'due_at' => 'nullable|date',
            'branch_id' => [
                'sometimes',
                'nullable',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'department_id' => [
                'sometimes',
                'nullable',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'project_id' => [
                'sometimes',
                'nullable',
                Rule::exists('projects', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'assignee_membership_id' => [
                'sometimes',
                'nullable',
                Rule::exists('company_user', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
        ]);

        $task->update($validated);

        AuditEvent::log('task.updated', 'task', $task->id, $validated);

        return response()->json([
            'message' => 'Task updated successfully.',
            'task' => $task->fresh()->load(['branch', 'department', 'project', 'assignee.user']),
        ]);
    }

    public function transition(Request $request, string $id): JsonResponse
    {
        $task = Task::findOrFail($id);
        $companyId = session('current_company_id');
        $membership = $request->user()->membershipIn($companyId);

        $validated = $request->validate([
            'status' => 'required|in:backlog,todo,in_progress,blocked,done,cancelled',
            'note' => 'nullable|string|max:500',
        ]);

        Gate::authorize('transition', [$task, $validated['status']]);

        $task = $task->transitionStatus(
            newStatus: $validated['status'],
            actorMembershipId: $membership->id,
            note: $validated['note'] ?? null
        );

        return response()->json([
            'message' => 'Task status transitioned successfully.',
            'task' => $task->load(['statusHistory.actor.user']),
        ]);
    }

    public function addComment(Request $request, string $id): JsonResponse
    {
        $task = Task::findOrFail($id);
        Gate::authorize('view', $task);

        $companyId = session('current_company_id');
        $membership = $request->user()->membershipIn($companyId);

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $comment = TaskComment::create([
            'id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'task_id' => $task->id,
            'author_membership_id' => $membership->id,
            'body' => $validated['body'],
        ]);

        AuditEvent::log('task.comment_added', 'task_comment', $comment->id, [
            'task_id' => $task->id,
        ]);

        return response()->json([
            'message' => 'Comment added successfully.',
            'comment' => $comment->load('author.user'),
        ], 201);
    }

    public function archive(string $id): JsonResponse
    {
        $task = Task::findOrFail($id);
        Gate::authorize('delete', $task);

        $task->update([
            'archived_at' => now(),
        ]);

        if ($task->project_id) {
            $task->project?->recalculateProgress();
        }

        AuditEvent::log('task.archived', 'task', $task->id);

        return response()->json([
            'message' => 'Task archived successfully.',
            'task' => $task->fresh(),
        ]);
    }
}
