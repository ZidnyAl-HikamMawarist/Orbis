<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\AuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = session('current_company_id');

        $query = Alert::where('company_id', $companyId)
            ->with(['branch:id,name,code', 'department:id,name,code', 'task:id,title,code']);

        if ($request->filled('severity')) {
            $query->where('severity', $request->query('severity'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        } else {
            $query->whereIn('status', ['open', 'acknowledged']);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        $alerts = $query->orderBy('created_at', 'desc')->paginate(30);

        return response()->json($alerts);
    }

    public function acknowledge(Request $request, string $id): JsonResponse
    {
        $companyId = session('current_company_id');
        $membership = $request->user()->membershipIn($companyId);

        $alert = Alert::where('company_id', $companyId)->findOrFail($id);

        $alert->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by_membership_id' => $membership->id,
        ]);

        AuditEvent::log('alert.acknowledged', 'alert', $alert->id, [
            'severity' => $alert->severity,
        ]);

        return response()->json([
            'message' => 'Alert acknowledged successfully.',
            'alert' => $alert->fresh()->load('acknowledgedBy.user'),
        ]);
    }

    public function resolve(Request $request, string $id): JsonResponse
    {
        $companyId = session('current_company_id');

        $alert = Alert::where('company_id', $companyId)->findOrFail($id);

        $alert->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        AuditEvent::log('alert.resolved', 'alert', $alert->id);

        return response()->json([
            'message' => 'Alert marked as resolved.',
            'alert' => $alert->fresh(),
        ]);
    }
}
