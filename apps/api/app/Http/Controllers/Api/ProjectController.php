<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        $query = Project::query()
            ->with(['branch:id,name,code', 'department:id,name,code', 'lead.user:id,name'])
            ->withCount(['tasks', 'tasks as completed_tasks_count' => function ($q) {
                $q->where('status', 'done');
            }]);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->query('department_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        } else {
            $query->where('status', '!=', 'archived');
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $projects = $query->orderBy('created_at', 'desc')->paginate(30);

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Project::class);

        $companyId = session('current_company_id');

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('projects')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'name' => 'required|string|max:160',
            'description' => 'nullable|string',
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'lead_membership_id' => [
                'nullable',
                Rule::exists('company_user', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'status' => 'nullable|in:planned,active,on_hold,completed,cancelled',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $project = Project::create([
            'id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'branch_id' => $validated['branch_id'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'lead_membership_id' => $validated['lead_membership_id'] ?? null,
            'status' => $validated['status'] ?? 'planned',
            'progress_percent' => 0,
            'start_date' => $validated['start_date'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
        ]);

        AuditEvent::log('project.created', 'project', $project->id, [
            'name' => $project->name,
            'code' => $project->code,
        ]);

        return response()->json([
            'message' => 'Project created successfully.',
            'project' => $project->load(['branch', 'department', 'lead.user']),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $project = Project::with([
            'branch',
            'department',
            'lead.user',
            'tasks' => function ($q) {
                $q->whereNull('archived_at')->with(['assignee.user'])->orderBy('due_at', 'asc');
            },
        ])->findOrFail($id);

        Gate::authorize('view', $project);

        return response()->json(['project' => $project]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $project = Project::findOrFail($id);
        Gate::authorize('update', $project);

        $companyId = session('current_company_id');

        $validated = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:40',
                Rule::unique('projects')->where(fn ($q) => $q->where('company_id', $companyId))->ignore($project->id),
            ],
            'name' => 'sometimes|required|string|max:160',
            'description' => 'nullable|string',
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
            'lead_membership_id' => [
                'sometimes',
                'nullable',
                Rule::exists('company_user', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'status' => 'sometimes|required|in:planned,active,on_hold,completed,cancelled,archived',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $project->update($validated);

        AuditEvent::log('project.updated', 'project', $project->id, $validated);

        return response()->json([
            'message' => 'Project updated successfully.',
            'project' => $project->fresh()->load(['branch', 'department', 'lead.user']),
        ]);
    }

    public function archive(string $id): JsonResponse
    {
        $project = Project::findOrFail($id);
        Gate::authorize('archive', $project);

        $project->update([
            'status' => 'archived',
            'archived_at' => now(),
        ]);

        AuditEvent::log('project.archived', 'project', $project->id);

        return response()->json([
            'message' => 'Project archived successfully.',
            'project' => $project->fresh(),
        ]);
    }
}
