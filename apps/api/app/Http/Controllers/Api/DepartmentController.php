<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Department::class);

        $query = Department::query()->with('branch');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
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

        $departments = $query->orderBy('name', 'asc')->paginate(50);

        return response()->json($departments);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Department::class);

        $companyId = session('current_company_id');

        $validated = $request->validate([
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'code' => 'required|string|max:40',
            'name' => 'required|string|max:160',
            'status' => 'nullable|in:active,inactive',
        ]);

        $code = strtoupper($validated['code']);
        $branchId = $validated['branch_id'] ?? null;

        // Verify scope_key uniqueness
        $scopeKey = $companyId . ':' . ($branchId ?? '__GLOBAL__') . ':' . $code;
        if (Department::where('scope_key', $scopeKey)->exists()) {
            return response()->json([
                'message' => 'The department code already exists in this branch scope.',
                'errors' => ['code' => ['The code is already taken for this scope.']],
            ], 422);
        }

        $department = Department::create([
            'id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'code' => $code,
            'name' => $validated['name'],
            'status' => $validated['status'] ?? 'active',
        ]);

        AuditEvent::log('department.created', 'department', $department->id, [
            'name' => $department->name,
            'code' => $department->code,
            'branch_id' => $department->branch_id,
        ]);

        return response()->json([
            'message' => 'Department created successfully.',
            'department' => $department->load('branch'),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $department = Department::with('branch')->findOrFail($id);
        Gate::authorize('view', $department);

        return response()->json(['department' => $department]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $department = Department::findOrFail($id);
        Gate::authorize('update', $department);

        $companyId = session('current_company_id');

        $validated = $request->validate([
            'branch_id' => [
                'sometimes',
                'nullable',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'code' => 'sometimes|required|string|max:40',
            'name' => 'sometimes|required|string|max:160',
            'status' => 'sometimes|required|in:active,inactive,archived',
        ]);

        $code = isset($validated['code']) ? strtoupper($validated['code']) : $department->code;
        $branchId = array_key_exists('branch_id', $validated) ? $validated['branch_id'] : $department->branch_id;

        $scopeKey = $companyId . ':' . ($branchId ?? '__GLOBAL__') . ':' . $code;
        if (Department::where('scope_key', $scopeKey)->where('id', '!=', $department->id)->exists()) {
            return response()->json([
                'message' => 'The department code already exists in this branch scope.',
                'errors' => ['code' => ['The code is already taken for this scope.']],
            ], 422);
        }

        if (isset($validated['code'])) {
            $validated['code'] = $code;
        }

        $department->update($validated);

        AuditEvent::log('department.updated', 'department', $department->id, $validated);

        return response()->json([
            'message' => 'Department updated successfully.',
            'department' => $department->fresh()->load('branch'),
        ]);
    }

    public function archive(string $id): JsonResponse
    {
        $department = Department::findOrFail($id);
        Gate::authorize('archive', $department);

        $department->update([
            'status' => 'archived',
            'archived_at' => now(),
        ]);

        AuditEvent::log('department.archived', 'department', $department->id);

        return response()->json([
            'message' => 'Department archived successfully.',
            'department' => $department->fresh(),
        ]);
    }
}
