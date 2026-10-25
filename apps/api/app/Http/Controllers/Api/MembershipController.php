<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\CompanyUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MembershipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CompanyUser::class);

        $companyId = session('current_company_id');

        $query = CompanyUser::where('company_id', $companyId)
            ->with(['user:id,name,email,created_at', 'inviter.user:id,name']);

        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $members = $query->orderBy('created_at', 'asc')->paginate(50);

        return response()->json($members);
    }

    public function updateRole(Request $request, string $id): JsonResponse
    {
        $companyId = session('current_company_id');
        $membership = CompanyUser::where('company_id', $companyId)->findOrFail($id);

        Gate::authorize('updateRole', $membership);

        $validated = $request->validate([
            'role' => 'required|in:company_admin,operations_manager,branch_manager,department_lead,staff,auditor',
        ]);

        $oldRole = $membership->role;
        $membership->update(['role' => $validated['role']]);

        AuditEvent::log('membership.role_updated', 'company_user', $membership->id, [
            'from_role' => $oldRole,
            'to_role' => $validated['role'],
        ]);

        return response()->json([
            'message' => 'Member role updated successfully.',
            'membership' => $membership->fresh()->load('user'),
        ]);
    }

    public function deactivate(string $id): JsonResponse
    {
        $companyId = session('current_company_id');
        $membership = CompanyUser::where('company_id', $companyId)->findOrFail($id);

        Gate::authorize('deactivate', $membership);

        $membership->update(['status' => 'inactive']);

        AuditEvent::log('membership.deactivated', 'company_user', $membership->id, [
            'user_id' => $membership->user_id,
        ]);

        return response()->json([
            'message' => 'Member deactivated successfully.',
            'membership' => $membership->fresh(),
        ]);
    }
}
