<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Project;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KpiCalculationService
{
    public function calculateDashboardMetrics(
        string $companyId,
        ?string $branchId = null,
        ?string $departmentId = null
    ): array {
        $company = Company::findOrFail($companyId);
        $now = now();

        // 30-day window for completion rate
        $windowStart = $now->copy()->subDays(30)->startOfDay();
        $windowEnd = $now->copy()->endOfDay();

        // Base task query builder scoped to tenant and optional branch/dept
        $taskBase = Task::where('company_id', $companyId)->whereNull('archived_at');
        if ($branchId) {
            $taskBase->where('branch_id', $branchId);
        }
        if ($departmentId) {
            $taskBase->where('department_id', $departmentId);
        }

        // 1. Open Task Count
        $openTasksCount = (clone $taskBase)
            ->whereIn('status', ['backlog', 'todo', 'in_progress', 'blocked'])
            ->count();

        // 2. Overdue Task Count (tasks with due_at in the past, non-completed)
        $overdueTasksCount = (clone $taskBase)
            ->whereIn('status', ['backlog', 'todo', 'in_progress', 'blocked'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->count();

        // 3. Task Overdue Rate (overdue / open * 100)
        $taskOverdueRate = $openTasksCount > 0
            ? round(($overdueTasksCount / $openTasksCount) * 100, 1)
            : 0.0;

        // 4. Task Completion Rate in Window
        $completedInWindow = (clone $taskBase)
            ->where('status', 'done')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$windowStart, $windowEnd])
            ->count();

        $eligibleInWindow = (clone $taskBase)
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '<=', $windowEnd)
            ->where(function ($q) use ($windowStart) {
                $q->whereNull('completed_at')
                  ->orWhere('completed_at', '>=', $windowStart);
            })
            ->count();

        $taskCompletionRate = $eligibleInWindow > 0
            ? round(($completedInWindow / $eligibleInWindow) * 100, 1)
            : 0.0;

        // 5. Task Distribution by status
        $statusCounts = (clone $taskBase)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $distribution = [
            'backlog' => $statusCounts['backlog'] ?? 0,
            'todo' => $statusCounts['todo'] ?? 0,
            'in_progress' => $statusCounts['in_progress'] ?? 0,
            'blocked' => $statusCounts['blocked'] ?? 0,
            'done' => $statusCounts['done'] ?? 0,
            'cancelled' => $statusCounts['cancelled'] ?? 0,
        ];

        // 6. Active Projects count and average progress
        $projectQuery = Project::where('company_id', $companyId)->whereNull('archived_at');
        if ($branchId) {
            $projectQuery->where('branch_id', $branchId);
        }
        if ($departmentId) {
            $projectQuery->where('department_id', $departmentId);
        }

        $activeProjectsCount = (clone $projectQuery)->whereIn('status', ['planned', 'active', 'on_hold'])->count();
        $averageProjectProgress = round((float) (clone $projectQuery)->avg('progress_percent') ?? 0, 1);

        // 7. Alerts count
        $alertBase = Alert::where('company_id', $companyId)->whereIn('status', ['open', 'acknowledged']);
        if ($branchId) {
            $alertBase->where('branch_id', $branchId);
        }
        $criticalAlerts = (clone $alertBase)->where('severity', 'critical')->count();
        $warningAlerts = (clone $alertBase)->where('severity', 'warning')->count();
        $infoAlerts = (clone $alertBase)->where('severity', 'info')->count();

        // 8. Overall Operational Status
        $operationalStatus = 'operational';
        if ($criticalAlerts > 0 || $overdueTasksCount >= 5) {
            $operationalStatus = 'critical';
        } elseif ($warningAlerts > 0 || ($overdueTasksCount >= 2 && $overdueTasksCount <= 4)) {
            $operationalStatus = 'warning';
        }

        return [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'operational_status' => $operationalStatus,
            'kpis' => [
                'open_task_count' => [
                    'key' => 'open_task_count',
                    'label' => 'Open Tasks',
                    'value' => $openTasksCount,
                    'unit' => 'count',
                ],
                'overdue_task_count' => [
                    'key' => 'overdue_task_count',
                    'label' => 'Overdue Tasks',
                    'value' => $overdueTasksCount,
                    'unit' => 'count',
                ],
                'task_overdue_rate' => [
                    'key' => 'task_overdue_rate',
                    'label' => 'Task Overdue Rate',
                    'value' => $taskOverdueRate,
                    'unit' => 'percent',
                ],
                'task_completion_rate' => [
                    'key' => 'task_completion_rate',
                    'label' => 'Task Completion Rate',
                    'value' => $taskCompletionRate,
                    'unit' => 'percent',
                    'window_start' => $windowStart->toIso8601String(),
                    'window_end' => $windowEnd->toIso8601String(),
                ],
                'active_projects_count' => [
                    'key' => 'active_projects_count',
                    'label' => 'Active Projects',
                    'value' => $activeProjectsCount,
                    'unit' => 'count',
                ],
                'average_project_progress' => [
                    'key' => 'average_project_progress',
                    'label' => 'Avg Project Progress',
                    'value' => $averageProjectProgress,
                    'unit' => 'percent',
                ],
            ],
            'distribution' => $distribution,
            'alerts_summary' => [
                'critical' => $criticalAlerts,
                'warning' => $warningAlerts,
                'info' => $infoAlerts,
                'total_active' => $criticalAlerts + $warningAlerts + $infoAlerts,
            ],
        ];
    }

    public function calculateBranchOperationalStatus(string $branchId): array
    {
        $branch = Branch::findOrFail($branchId);
        $companyId = $branch->company_id;
        $now = now();

        $overdueCount = Task::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereNull('archived_at')
            ->whereIn('status', ['backlog', 'todo', 'in_progress', 'blocked'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->count();

        $criticalAlerts = Alert::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereIn('status', ['open', 'acknowledged'])
            ->where('severity', 'critical')
            ->count();

        $warningAlerts = Alert::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereIn('status', ['open', 'acknowledged'])
            ->where('severity', 'warning')
            ->count();

        $status = 'operational';
        if ($criticalAlerts > 0 || $overdueCount >= 5) {
            $status = 'critical';
        } elseif ($warningAlerts > 0 || ($overdueCount >= 2 && $overdueCount <= 4)) {
            $status = 'warning';
        }

        return [
            'branch_id' => $branchId,
            'status' => $status,
            'overdue_count' => $overdueCount,
            'critical_alerts' => $criticalAlerts,
            'warning_alerts' => $warningAlerts,
        ];
    }
}
