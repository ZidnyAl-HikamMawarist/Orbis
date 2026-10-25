<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;
    private CompanyUser $membership;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'id' => (string) Str::ulid(),
            'name' => 'Index Benchmark Co',
            'slug' => 'index-bench-co',
            'status' => 'active',
        ]);

        $this->user = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Index Auditor',
            'email' => 'auditor@index.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $this->membership = CompanyUser::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    /**
     * Test that composite listing indexes exist on tasks and projects tables.
     */
    public function test_composite_listing_indexes_exist(): void
    {
        $taskIndexes = DB::select("SHOW INDEXES FROM tasks WHERE Key_name = 'ix_task_listing_created'");
        $this->assertNotEmpty($taskIndexes, 'Index ix_task_listing_created should exist on tasks table.');

        $projectIndexes = DB::select("SHOW INDEXES FROM projects WHERE Key_name = 'ix_project_listing_created'");
        $this->assertNotEmpty($projectIndexes, 'Index ix_project_listing_created should exist on projects table.');
    }

    /**
     * Test that TaskController::index returns correctly ordered paginated results using new index.
     */
    public function test_task_listing_ordered_chronologically_descending(): void
    {
        $now = now();
        $task1 = Task::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->company->id,
            'creator_membership_id' => $this->membership->id,
            'code' => 'TSK-ORDER-01',
            'title' => 'First Created Task',
            'created_at' => $now->copy()->subHours(2),
        ]);

        $task2 = Task::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->company->id,
            'creator_membership_id' => $this->membership->id,
            'code' => 'TSK-ORDER-02',
            'title' => 'Second Created Task',
            'created_at' => $now->copy()->subHour(),
        ]);

        $task3 = Task::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->company->id,
            'creator_membership_id' => $this->membership->id,
            'code' => 'TSK-ORDER-03',
            'title' => 'Third Created Task',
            'created_at' => $now,
        ]);

        $this->actingAs($this->user);
        session(['current_company_id' => $this->company->id]);

        $response = $this->getJson('/api/v1/tasks?per_page=10');
        $response->assertStatus(200);

        $items = $response->json('data');
        $this->assertCount(3, $items);
        $this->assertEquals($task3->id, $items[0]['id'], 'Newest task should be first.');
        $this->assertEquals($task2->id, $items[1]['id'], 'Middle task should be second.');
        $this->assertEquals($task1->id, $items[2]['id'], 'Oldest task should be third.');
    }

    /**
     * Test that archived tasks are excluded from primary index scan by default.
     */
    public function test_archived_tasks_excluded_from_default_listing(): void
    {
        $activeTask = Task::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->company->id,
            'creator_membership_id' => $this->membership->id,
            'code' => 'TSK-ACTIVE-01',
            'title' => 'Active Operational Task',
            'archived_at' => null,
        ]);

        $archivedTask = Task::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->company->id,
            'creator_membership_id' => $this->membership->id,
            'code' => 'TSK-ARCHIVED-01',
            'title' => 'Archived Completed Task',
            'archived_at' => now(),
        ]);

        $this->actingAs($this->user);
        session(['current_company_id' => $this->company->id]);

        $response = $this->getJson('/api/v1/tasks');
        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($activeTask->id, $ids);
        $this->assertNotContains($archivedTask->id, $ids);

        // Verify archived query parameter returns archived records
        $archivedResponse = $this->getJson('/api/v1/tasks?archived=true');
        $archivedResponse->assertStatus(200);

        $archivedIds = collect($archivedResponse->json('data'))->pluck('id')->all();
        $this->assertContains($archivedTask->id, $archivedIds);
        $this->assertNotContains($activeTask->id, $archivedIds);
    }
}
