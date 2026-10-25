<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\KpiCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private KpiCalculationService $kpiService
    ) {}

    public function metrics(Request $request): JsonResponse
    {
        $companyId = session('current_company_id');
        $branchId = $request->query('branch_id');
        $departmentId = $request->query('department_id');

        // If branch_id provided, ensure it belongs to current company
        if ($branchId) {
            Branch::where('company_id', $companyId)->findOrFail($branchId);
        }

        $metrics = $this->kpiService->calculateDashboardMetrics($companyId, $branchId, $departmentId);

        // Include branch summary list with operational status for 3D world / cards
        $branches = Branch::where('company_id', $companyId)
            ->where('status', 'active')
            ->get()
            ->map(function ($branch) {
                $statusInfo = $this->kpiService->calculateBranchOperationalStatus($branch->id);
                return [
                    'id' => $branch->id,
                    'code' => $branch->code,
                    'name' => $branch->name,
                    'operational_status' => $statusInfo['status'],
                    'overdue_count' => $statusInfo['overdue_count'],
                    'critical_alerts' => $statusInfo['critical_alerts'],
                ];
            });

        $metrics['branches'] = $branches;

        return response()->json($metrics);
    }
}
