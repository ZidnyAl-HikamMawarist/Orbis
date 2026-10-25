<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ReportController;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CsvSanitizationTest extends TestCase
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
            'name' => 'CSV Test Org',
            'slug' => 'csv-test-org',
            'timezone' => 'UTC',
            'status' => 'active',
        ]);

        $this->user = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'CSV Auditor',
            'email' => 'auditor@csv.local',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $this->membership = CompanyUser::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'role' => 'company_admin',
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    /**
     * Test direct unit edge-cases on sanitizeCsvCell helper.
     */
    public function test_sanitize_csv_cell_edge_cases(): void
    {
        $controller = new ReportController();

        // 1. Direct formula triggers
        $this->assertEquals("'=1+1", $controller->sanitizeCsvCell('=1+1'));
        $this->assertEquals("'+cmd|' /C calc'!A0", $controller->sanitizeCsvCell("+cmd|' /C calc'!A0"));
        $this->assertEquals("'-2+3", $controller->sanitizeCsvCell('-2+3'));
        $this->assertEquals("'@SUM(1+1)", $controller->sanitizeCsvCell('@SUM(1+1)'));
        $this->assertEquals("'%0Acalc", $controller->sanitizeCsvCell('%0Acalc'));
        $this->assertEquals("'|calc", $controller->sanitizeCsvCell('|calc'));

        // 2. Leading whitespace obfuscation
        $this->assertEquals("'   =1+1", $controller->sanitizeCsvCell('   =1+1'));
        $this->assertEquals("'  +cmd", $controller->sanitizeCsvCell('  +cmd'));
        $this->assertEquals("' \t -calc", $controller->sanitizeCsvCell(" \t -calc"));
        $this->assertEquals("'  @HYPERLINK", $controller->sanitizeCsvCell('  @HYPERLINK'));

        // 3. Leading tabs and carriage returns
        $this->assertEquals("'\t=cmd", $controller->sanitizeCsvCell("\t=cmd"));
        $this->assertEquals("'\r+2+2", $controller->sanitizeCsvCell("\r+2+2"));
        $this->assertEquals("'\n@calc", $controller->sanitizeCsvCell("\n@calc"));

        // 4. Legitimate data preserved
        $this->assertEquals('Normal Task Title', $controller->sanitizeCsvCell('Normal Task Title'));
        $this->assertEquals('TSK-1001', $controller->sanitizeCsvCell('TSK-1001'));
        $this->assertEquals('2026-10-25T17:00:00+00:00', $controller->sanitizeCsvCell('2026-10-25T17:00:00+00:00'));
        $this->assertEquals(12345, $controller->sanitizeCsvCell(12345));
        $this->assertNull($controller->sanitizeCsvCell(null));
    }

    /**
     * Test streamed CSV export sanitizes malicious payloads across ALL fields.
     */
    public function test_streamed_csv_export_sanitizes_across_all_model_fields(): void
    {
        // Create branch with formula name
        $branch = Branch::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->company->id,
            'code' => 'BR-PAYLOAD',
            'name' => '+Branch=cmd|',
            'status' => 'active',
        ]);

        // Create department with formula name
        $dept = Department::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'code' => 'DEPT-PAYLOAD',
            'name' => '-Dept|calc',
            'status' => 'active',
        ]);

        // Create project with formula name
        $project = Project::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'department_id' => $dept->id,
            'code' => 'PRJ-PAYLOAD',
            'name' => '@ProjectSUM',
            'status' => 'active',
        ]);

        // Create malicious task with whitespace + formula title and formula code
        Task::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'department_id' => $dept->id,
            'project_id' => $project->id,
            'creator_membership_id' => $this->membership->id,
            'assignee_membership_id' => $this->membership->id,
            'code' => '=TSK-INJECT',
            'title' => '   =cmd|\' /C calc\'!A0',
            'status' => 'todo',
            'priority' => 'urgent',
            'due_at' => now(),
        ]);

        // Also create a normal task to verify legitimate data is unharmed
        Task::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->company->id,
            'creator_membership_id' => $this->membership->id,
            'code' => 'TSK-CLEAN',
            'title' => 'Regular Operations Task',
            'status' => 'done',
            'priority' => 'normal',
        ]);

        $this->actingAs($this->user);
        session(['current_company_id' => $this->company->id]);

        $response = $this->get('/api/v1/reports/tasks-csv');

        $response->assertStatus(200);
        $content = $response->streamedContent();

        // 1. Verify Task Code escaped
        $this->assertStringContainsString("'=TSK-INJECT", $content);

        // 2. Verify Task Title with leading space escaped
        $this->assertStringContainsString("'   =cmd|' /C calc'!A0", $content);

        // 3. Verify Branch Name escaped
        $this->assertStringContainsString("'+Branch=cmd|", $content);

        // 4. Verify Dept Name escaped
        $this->assertStringContainsString("'-Dept|calc", $content);

        // 5. Verify Project Name escaped
        $this->assertStringContainsString("'@ProjectSUM", $content);

        // 6. Verify clean task is not prepended with single quote
        $this->assertStringContainsString(',TSK-CLEAN,"Regular Operations Task",', $content);
        $this->assertStringNotContainsString("',TSK-CLEAN", $content);
        $this->assertStringNotContainsString("'Regular Operations Task", $content);
    }
}
