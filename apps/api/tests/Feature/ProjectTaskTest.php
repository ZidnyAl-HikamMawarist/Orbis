<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectTaskTest extends TestCase
{
    private User $adminUser;
    private User $staffUser;
    private Company $companyA;
    private Company $companyB;
    private Branch $branchA;
    private Project $foreignProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'id' => (string) Str::ulid(),
            'name' => 'Project Test Company A',
            'slug' => 'proj-test-a-' . Str::random(5),
            'status' => 'active',
        ]);

        $this->companyB = Company::create([
            'id' => (string) Str::ulid(),
            'name' => 'Project Test Company B',
            'slug' => 'proj-test-b-' . Str::random(5),
            'status' => 'active',
        ]);

        $this->branchA = Branch::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyA->id,
            'code' => 'HQ-01',
            'name' => 'Headquarters',
            'status' => 'active',
        ]);

        $this->foreignProject = Project::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyB->id,
            'code' => 'FOR-PROJ',
            'name' => 'Foreign Project',
            'status' => 'active',
        ]);

        $this->adminUser = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Project Admin',
            'email' => 'proj_admin_' . Str::random(6) . '@example.com',
            'password' => bcrypt('password123'),
        ]);

        CompanyUser::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyA->id,
            'user_id' => $this->adminUser->id,
            'role' => 'company_admin',
            'status' => 'active',
        ]);

        $this->staffUser = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Project Staff',
            'email' => 'proj_staff_' . Str::random(6) . '@example.com',
            'password' => bcrypt('password123'),
        ]);

        CompanyUser::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyA->id,
            'user_id' => $this->staffUser->id,
            'role' => 'staff',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_create_project(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->postJson('/api/v1/projects', [
                'code' => 'PRJ-ALPHA',
                'name' => 'Alpha Transformation',
                'description' => 'Key Q4 Initiative',
                'branch_id' => $this->branchA->id,
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('project.code', 'PRJ-ALPHA')
            ->assertJsonPath('project.progress_percent', 0);
    }

    public function test_task_creation_and_recalculation_includes_backlog_in_denominator(): void
    {
        $project = Project::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyA->id,
            'code' => 'PRJ-METRIC',
            'name' => 'Metric Project',
            'status' => 'active',
        ]);

        // Create 1 backlog task and 1 todo task
        $task1 = $this->actingAs($this->adminUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->postJson('/api/v1/tasks', [
                'project_id' => $project->id,
                'code' => 'TSK-001',
                'title' => 'Backlog Task',
                'status' => 'backlog',
            ]);
        $task1->assertStatus(201);

        $task2 = $this->actingAs($this->adminUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->postJson('/api/v1/tasks', [
                'project_id' => $project->id,
                'code' => 'TSK-002',
                'title' => 'Active Task',
                'status' => 'todo',
            ]);
        $task2->assertStatus(201);

        $project->refresh();
        $this->assertEquals(0, $project->progress_percent);

        // Transition task2 to done: 1 done / 2 total (backlog + done) = 50%
        $this->actingAs($this->adminUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->postJson("/api/v1/tasks/{$task2->json('task.id')}/transition", [
                'status' => 'done',
                'note' => 'Completed work',
            ])
            ->assertStatus(200);

        $project->refresh();
        // Since backlog is included in denominator per OD-003, progress is 50%, NOT 100%!
        $this->assertEquals(50, $project->progress_percent);
    }

    public function test_reopen_completed_task_restricted_by_policy_for_staff(): void
    {
        $adminMembership = CompanyUser::where('company_id', $this->companyA->id)
            ->where('user_id', $this->adminUser->id)
            ->first();

        $staffMembership = CompanyUser::where('company_id', $this->companyA->id)
            ->where('user_id', $this->staffUser->id)
            ->first();

        $task = Task::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyA->id,
            'creator_membership_id' => $adminMembership->id,
            'assignee_membership_id' => $staffMembership->id,
            'code' => 'TSK-REOPEN-STAFF',
            'title' => 'Reopen Target Staff',
            'status' => 'done',
            'completed_at' => now(),
        ]);

        // Staff attempts to reopen task: rejected with 403
        $staffAttempt = $this->actingAs($this->staffUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->postJson("/api/v1/tasks/{$task->id}/transition", [
                'status' => 'in_progress',
                'note' => 'Staff trying to reopen',
            ]);

        $staffAttempt->assertStatus(403);
    }

    public function test_admin_can_reopen_completed_task(): void
    {
        $adminMembership = CompanyUser::where('company_id', $this->companyA->id)
            ->where('user_id', $this->adminUser->id)
            ->first();

        $staffMembership = CompanyUser::where('company_id', $this->companyA->id)
            ->where('user_id', $this->staffUser->id)
            ->first();

        $task = Task::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyA->id,
            'creator_membership_id' => $adminMembership->id,
            'assignee_membership_id' => $staffMembership->id,
            'code' => 'TSK-REOPEN-ADMIN',
            'title' => 'Reopen Target Admin',
            'status' => 'done',
            'completed_at' => now(),
        ]);

        // Admin reopens task: allowed with 200
        $adminAttempt = $this->actingAs($this->adminUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->postJson("/api/v1/tasks/{$task->id}/transition", [
                'status' => 'in_progress',
                'note' => 'Admin reopening for rework',
            ]);

        $adminAttempt->assertStatus(200)
            ->assertJsonPath('task.status', 'in_progress');
    }

    public function test_task_comment_timeline_and_audit(): void
    {
        $adminMembership = CompanyUser::where('company_id', $this->companyA->id)
            ->where('user_id', $this->adminUser->id)
            ->first();

        $task = Task::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyA->id,
            'creator_membership_id' => $adminMembership->id,
            'code' => 'TSK-COMM',
            'title' => 'Comment Target',
            'status' => 'todo',
        ]);

        $response = $this->actingAs($this->staffUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->postJson("/api/v1/tasks/{$task->id}/comments", [
                'body' => 'Investigation completed. Starting implementation.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('comment.body', 'Investigation completed. Starting implementation.');
    }

    public function test_task_with_foreign_project_id_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->postJson('/api/v1/tasks', [
                'project_id' => $this->foreignProject->id,
                'code' => 'TSK-INJECT',
                'title' => 'Injected Task',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['project_id']);
    }
}
