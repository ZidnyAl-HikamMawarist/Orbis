<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Branch::class);

        $query = Branch::query()
            ->withCount(['departments', 'tasks as active_tasks_count' => function ($q) {
                $q->whereNotIn('status', ['done', 'cancelled']);
            }]);

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

        $branches = $query->orderBy('name', 'asc')->paginate(50);

        return response()->json($branches);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Branch::class);

        $companyId = session('current_company_id');

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('branches')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'name' => 'required|string|max:160',
            'address' => 'nullable|string|max:500',
            'timezone' => 'nullable|string|max:64',
            'status' => 'nullable|in:active,inactive',
        ]);

        $branch = Branch::create([
            'id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'timezone' => $validated['timezone'] ?? 'UTC',
            'status' => $validated['status'] ?? 'active',
        ]);

        AuditEvent::log('branch.created', 'branch', $branch->id, [
            'name' => $branch->name,
            'code' => $branch->code,
        ]);

        return response()->json([
            'message' => 'Branch created successfully.',
            'branch' => $branch,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $branch = Branch::with(['departments' => function ($q) {
            $q->where('status', '!=', 'archived');
        }])->findOrFail($id);

        Gate::authorize('view', $branch);

        return response()->json(['branch' => $branch]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);
        Gate::authorize('update', $branch);

        $companyId = session('current_company_id');

        $validated = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:40',
                Rule::unique('branches')->where(fn ($q) => $q->where('company_id', $companyId))->ignore($branch->id),
            ],
            'name' => 'sometimes|required|string|max:160',
            'address' => 'nullable|string|max:500',
            'timezone' => 'nullable|string|max:64',
            'status' => 'sometimes|required|in:active,inactive,archived',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $branch->update($validated);

        AuditEvent::log('branch.updated', 'branch', $branch->id, $validated);

        return response()->json([
            'message' => 'Branch updated successfully.',
            'branch' => $branch->fresh(),
        ]);
    }

    public function archive(string $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);
        Gate::authorize('archive', $branch);

        $branch->update([
            'status' => 'archived',
            'archived_at' => now(),
        ]);

        AuditEvent::log('branch.archived', 'branch', $branch->id);

        return response()->json([
            'message' => 'Branch archived successfully.',
            'branch' => $branch->fresh(),
        ]);
    }
}
