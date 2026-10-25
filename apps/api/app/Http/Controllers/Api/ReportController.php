<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Task;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function tasksCsv(Request $request): StreamedResponse
    {
        $companyId = session('current_company_id');

        $query = Task::where('company_id', $companyId)
            ->whereNull('archived_at')
            ->with(['branch', 'department', 'project', 'assignee.user']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->query('project_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        AuditEvent::log('report.exported_tasks_csv', 'report', null, [
            'filters' => $request->only(['branch_id', 'project_id', 'status']),
        ]);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="orbis-tasks-export-' . now()->format('Y-m-d') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // Header row
            fputcsv($handle, [
                'Task ID',
                'Code',
                'Title',
                'Status',
                'Priority',
                'Project',
                'Branch',
                'Department',
                'Assignee',
                'Due Date',
                'Created At',
            ]);

            $query->chunk(100, function ($tasks) use ($handle) {
                foreach ($tasks as $task) {
                    $row = [
                        $task->id,
                        $task->code,
                        $task->title,
                        $task->status,
                        $task->priority,
                        $task->project?->name ?? 'N/A',
                        $task->branch?->name ?? 'N/A',
                        $task->department?->name ?? 'N/A',
                        $task->assignee?->user?->name ?? 'Unassigned',
                        $task->due_at?->toIso8601String() ?? 'No Due Date',
                        $task->created_at?->toIso8601String(),
                    ];

                    fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], $row));
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Prevent CSV Formula Injection (CWE-1236) by escaping formula characters.
     */
    private function sanitizeCsvCell(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (preg_match('/^[=\+\-@\t\r]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}
