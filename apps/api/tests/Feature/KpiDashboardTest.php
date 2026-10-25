<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class KpiDashboardTest extends TestCase
{
    private User $userA;
    private Company $companyA;
    private Company $companyB;
    private Branch $branchA;
    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'id' => (string) Str::ulid(),
            'name' => 'KPI Test Co A',
            'slug' => 'kpi-test-a-' . Str::random(5),
            'status' => 'active',
        ]);

        $this->companyB = Company::create([
            'id' => (string) Str::ulid(),
            'name' => 'KPI Test Co B',
            'slug' => 'kpi-test-b-' . Str::random(5),
            'status' => 'active',
        ]);

        $this->branchA = Branch::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyA->id,
            'code' => 'BR-A',
            'name' => 'Branch A',
            'status' => 'active',
        ]);

        $this->branchB = Branch::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyB->id,
            'code' => 'BR-B',
            'name' => 'Branch B',
            'status' => 'active',
        ]);

        $this->userA = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'KPI User A',
            'email' => 'kpi_user_' . Str::random(6) . '@example.com',
            'password' => bcrypt('password123'),
        ]);

        CompanyUser::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyA->id,
            'user_id' => $this->userA->id,
            'role' => 'company_admin',
            'status' => 'active',
        ]);
    }

    public function test_tc_kpi_01_empty_dataset_safe_fallbacks(): void
    {
        $response = $this->actingAs($this->userA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->getJson('/api/v1/dashboard/metrics');

        $response->assertStatus(200)
            ->assertJsonPath('kpis.open_task_count.value', 0)
            ->assertJsonPath('kpis.overdue_task_count.value', 0)
            ->assertJsonPath('kpis.task_overdue_rate.value', 0)
            ->assertJsonPath('kpis.task_completion_rate.value', 0)
            ->assertJsonPath('operational_status', 'operational');
    }

    public function test_tc_kpi_04_05_overdue_calculation_and_missing_due_at(): void
    {
        $membership = CompanyUser::where('company_id', $this->companyA->id)->first();

        // Task 1: overdue (due yesterday)
        Task::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyA->id,
            'creator_membership_id' => $membership->id,
            'branch_id' => $this->branchA->id,
            'code' => 'TSK-DUE-PAST',
            'title' => 'Overdue Task',
            'status' => 'in_progress',
            'due_at' => now()->subDay(),
        ]);

        // Task 2: future due date
        Task::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyA->id,
            'creator_membership_id' => $membership->id,
            'branch_id' => $this->branchA->id,
            'code' => 'TSK-DUE-FUT',
            'title' => 'Future Task',
            'status' => 'todo',
            'due_at' => now()->addDays(3),
        ]);

        // Task 3: missing due_at (must NOT be counted as overdue!)
        Task::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyA->id,
            'creator_membership_id' => $membership->id,
            'branch_id' => $this->branchA->id,
            'code' => 'TSK-NO-DUE',
            'title' => 'No Deadline Task',
            'status' => 'backlog',
            'due_at' => null,
        ]);

        $response = $this->actingAs($this->userA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->getJson('/api/v1/dashboard/metrics');

        $response->assertStatus(200)
            ->assertJsonPath('kpis.open_task_count.value', 3)
            ->assertJsonPath('kpis.overdue_task_count.value', 1)
            ->assertJsonPath('kpis.task_overdue_rate.value', 33.3);
    }

    public function test_tc_kpi_06_branch_status_evaluates_critical_when_overdue_ge_5(): void
    {
        $membership = CompanyUser::where('company_id', $this->companyA->id)->first();

        for ($i = 1; $i <= 5; $i++) {
            Task::create([
                'id' => (string) Str::ulid(),
                'company_id' => $this->companyA->id,
                'creator_membership_id' => $membership->id,
                'branch_id' => $this->branchA->id,
                'code' => 'TSK-CRIT-' . $i,
                'title' => 'Overdue Task ' . $i,
                'status' => 'in_progress',
                'due_at' => now()->subDays($i),
            ]);
        }

        $response = $this->actingAs($this->userA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->getJson('/api/v1/dashboard/metrics');

        $response->assertStatus(200)
            ->assertJsonPath('operational_status', 'critical')
            ->assertJsonPath('branches.0.operational_status', 'critical');
    }

    public function test_alert_acknowledgment_and_listing(): void
    {
        $alert = Alert::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'source' => 'system_health',
            'dedupe_key' => 'sla_breach_test',
            'severity' => 'warning',
            'status' => 'open',
            'title' => 'High SLA Breaches Detected',
            'message' => 'Multiple tasks exceeded threshold.',
        ]);

        $list = $this->actingAs($this->userA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->getJson('/api/v1/alerts');

        $list->assertStatus(200)
            ->assertJsonPath('data.0.id', $alert->id);

        $ack = $this->actingAs($this->userA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->postJson("/api/v1/alerts/{$alert->id}/acknowledge");

        $ack->assertStatus(200)
            ->assertJsonPath('alert.status', 'acknowledged');
    }

    public function test_tasks_csv_export_enforces_tenant_boundary(): void
    {
        $membership = CompanyUser::where('company_id', $this->companyA->id)->first();

        Task::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyA->id,
            'creator_membership_id' => $membership->id,
            'code' => 'TSK-EXP-A',
            'title' => 'Exportable Task A',
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->userA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get('/api/v1/reports/tasks-csv');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('TSK-EXP-A', $content);
        $this->assertStringContainsString('Exportable Task A', $content);
    }

    public function test_foreign_branch_filter_returns_404(): void
    {
        $response = $this->actingAs($this->userA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->getJson("/api/v1/dashboard/metrics?branch_id={$this->branchB->id}");

        $response->assertStatus(404);
    }
}
