<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\CompanyUser;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;
    private Company $companyB;
    private User $userA;
    private User $userB;
    private Branch $branchB;
    private Department $deptB;
    private Project $projectB;
    private Task $taskB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'id' => (string) Str::ulid(),
            'name' => 'Company A (Tenant Alpha)',
            'slug' => 'tenant-alpha',
            'timezone' => 'Asia/Jakarta',
            'status' => 'active',
        ]);

        $this->companyB = Company::create([
            'id' => (string) Str::ulid(),
            'name' => 'Company B (Tenant Beta)',
            'slug' => 'tenant-beta',
            'timezone' => 'Asia/Makassar',
            'status' => 'active',
        ]);

        $this->userA = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'User Alpha',
            'email' => 'user.a@alpha.local',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        CompanyUser::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyA->id,
            'user_id' => $this->userA->id,
            'role' => 'company_admin',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->userB = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'User Beta',
            'email' => 'user.b@beta.local',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $membershipB = CompanyUser::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyB->id,
            'user_id' => $this->userB->id,
            'role' => 'company_admin',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        // Seed resources belonging to Company B
        $this->branchB = Branch::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyB->id,
            'code' => 'BR-B1',
            'name' => 'Branch Beta Surabaya',
            'status' => 'active',
        ]);

        $this->deptB = Department::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyB->id,
            'branch_id' => $this->branchB->id,
            'code' => 'DEPT-B1',
            'name' => 'Operations Beta',
            'status' => 'active',
        ]);

        $this->projectB = Project::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyB->id,
            'branch_id' => $this->branchB->id,
            'department_id' => $this->deptB->id,
            'code' => 'PRJ-B1',
            'name' => 'Borneo Expansion',
            'status' => 'active',
        ]);

        $this->taskB = Task::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyB->id,
            'branch_id' => $this->branchB->id,
            'department_id' => $this->deptB->id,
            'project_id' => $this->projectB->id,
            'creator_membership_id' => $membershipB->id,
            'code' => 'TSK-B1',
            'title' => 'Confidential Beta Strategy',
            'status' => 'todo',
        ]);
    }

    /**
     * SEC-ISO-01: User in Company A cannot see invitations of Company B.
     */
    public function test_user_cannot_access_other_company_invitations(): void
    {
        CompanyInvitation::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyB->id,
            'email' => 'confidential.beta@beta.local',
            'role' => 'owner',
            'token_hash' => hash('sha256', 'beta-secret-token'),
            'invited_by_membership_id' => $this->userB->memberships()->first()->id,
            'status' => 'pending',
            'expires_at' => now()->addHours(72),
            'created_at' => now(),
        ]);

        $this->actingAs($this->userA);
        session(['current_company_id' => $this->companyA->id]);

        $response = $this->getJson('/api/v1/invitations');

        $response->assertStatus(200);
        $emails = collect($response->json('data'))->pluck('email');
        $this->assertFalse($emails->contains('confidential.beta@beta.local'));
    }

    /**
     * SEC-ISO-02: User in Company A cannot revoke an invitation belonging to Company B.
     */
    public function test_user_cannot_revoke_other_company_invitation(): void
    {
        $inviteB = CompanyInvitation::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyB->id,
            'email' => 'target@beta.local',
            'role' => 'staff',
            'token_hash' => hash('sha256', 'beta-token-xyz'),
            'invited_by_membership_id' => $this->userB->memberships()->first()->id,
            'status' => 'pending',
            'expires_at' => now()->addHours(72),
            'created_at' => now(),
        ]);

        $this->actingAs($this->userA);
        session(['current_company_id' => $this->companyA->id]);

        $response = $this->postJson("/api/v1/invitations/{$inviteB->id}/revoke");

        // Returns 404 because company_id is scoped to companyA
        $response->assertStatus(404);

        $this->assertDatabaseHas('company_invitations', [
            'id' => $inviteB->id,
            'status' => 'pending', // Not revoked!
        ]);
    }

    /**
     * SEC-ISO-03: Eloquent TenantScope automatically isolates queries to active session company.
     */
    public function test_eloquent_tenant_scope_filters_queries_to_active_company(): void
    {
        session(['current_company_id' => $this->companyA->id]);

        // Querying branches under companyA session returns 0 records even though companyB has records
        $branches = Branch::all();
        $this->assertCount(0, $branches);

        // Querying tasks returns 0 records
        $tasks = Task::all();
        $this->assertCount(0, $tasks);

        // Switch session context to companyB
        session(['current_company_id' => $this->companyB->id]);
        $this->assertCount(1, Branch::all());
        $this->assertCount(1, Task::all());
    }

    /**
     * SEC-ISO-05: User cannot inject or switch to a foreign company ID.
     */
    public function test_user_cannot_switch_to_foreign_company_id(): void
    {
        $this->actingAs($this->userA);
        session(['current_company_id' => $this->companyA->id]);

        $response = $this->postJson('/api/v1/auth/switch-company', [
            'company_id' => $this->companyB->id,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('code', 'MEMBERSHIP_NOT_FOUND');

        $this->assertEquals($this->companyA->id, session('current_company_id'));
    }

    /**
     * SEC-ISO-06: Attempt to invite with foreign branch_id is rejected with 422.
     */
    public function test_invite_with_foreign_branch_id_is_rejected(): void
    {
        $this->actingAs($this->userA);
        session(['current_company_id' => $this->companyA->id]);

        $response = $this->postJson('/api/v1/invitations', [
            'email' => 'newuser@alpha.local',
            'role' => 'staff',
            'branch_id' => $this->branchB->id, // Branch from Company B!
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['branch_id']);
    }

    /**
     * SEC-ISO-07: Direct ID access to another company's task returns 404 (IDOR prevention).
     */
    public function test_user_cannot_read_task_of_another_company_by_direct_id(): void
    {
        $this->actingAs($this->userA);
        session(['current_company_id' => $this->companyA->id]);

        $response = $this->getJson("/api/v1/tasks/{$this->taskB->id}");

        $response->assertStatus(404);
    }

    /**
     * SEC-ISO-08: Direct update of another company's task returns 404 (BOLA prevention).
     */
    public function test_user_cannot_update_task_of_another_company_by_direct_id(): void
    {
        $this->actingAs($this->userA);
        session(['current_company_id' => $this->companyA->id]);

        $response = $this->putJson("/api/v1/tasks/{$this->taskB->id}", [
            'title' => 'Maliciously Tampered Title',
        ]);

        $response->assertStatus(404);

        $this->assertEquals('Confidential Beta Strategy', $this->taskB->fresh()->title);
    }

    /**
     * SEC-ISO-09: Direct access to another company's project returns 404.
     */
    public function test_user_cannot_read_project_of_another_company_by_direct_id(): void
    {
        $this->actingAs($this->userA);
        session(['current_company_id' => $this->companyA->id]);

        $response = $this->getJson("/api/v1/projects/{$this->projectB->id}");

        $response->assertStatus(404);
    }

    /**
     * SEC-ISO-10: Creating task with foreign project_id is rejected with 422.
     */
    public function test_user_cannot_assign_task_to_foreign_project_id(): void
    {
        $this->actingAs($this->userA);
        session(['current_company_id' => $this->companyA->id]);

        $response = $this->postJson('/api/v1/tasks', [
            'code' => 'TSK-A-FOREIGN-PRJ',
            'title' => 'Cross Tenant Task Attempt',
            'project_id' => $this->projectB->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['project_id']);
    }

    /**
     * SEC-ISO-11: Creating task with foreign department_id is rejected with 422.
     */
    public function test_user_cannot_assign_task_to_foreign_department_id(): void
    {
        $this->actingAs($this->userA);
        session(['current_company_id' => $this->companyA->id]);

        $response = $this->postJson('/api/v1/tasks', [
            'code' => 'TSK-A-FOREIGN-DEPT',
            'title' => 'Cross Tenant Dept Attempt',
            'department_id' => $this->deptB->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['department_id']);
    }

    /**
     * SEC-ISO-12: Updating membership role of another company returns 404.
     */
    public function test_user_cannot_update_role_of_another_company_member(): void
    {
        $membershipB = CompanyUser::where('company_id', $this->companyB->id)->first();

        $this->actingAs($this->userA);
        session(['current_company_id' => $this->companyA->id]);

        $response = $this->putJson("/api/v1/members/{$membershipB->id}/role", [
            'role' => 'staff',
        ]);

        $response->assertStatus(404);
    }

    /**
     * SEC-ISO-13: Task listing filtered by foreign branch returns empty collection, no leak.
     */
    public function test_task_listing_filtered_by_foreign_branch_returns_empty(): void
    {
        $this->actingAs($this->userA);
        session(['current_company_id' => $this->companyA->id]);

        $response = $this->getJson("/api/v1/tasks?branch_id={$this->branchB->id}");

        $response->assertStatus(200)
            ->assertJsonPath('total', 0);
    }

    /**
     * SEC-ISO-14: CSV export with formula character is safely escaped (CWE-1236 defense).
     */
    public function test_csv_export_sanitizes_potential_formula_injection(): void
    {
        $adminMembershipA = CompanyUser::where('company_id', $this->companyA->id)->first();

        Task::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyA->id,
            'creator_membership_id' => $adminMembershipA->id,
            'code' => 'TSK-FORMULA',
            'title' => '=SUM(1+1)',
            'status' => 'todo',
        ]);

        $this->actingAs($this->userA);
        session(['current_company_id' => $this->companyA->id]);

        $response = $this->get('/api/v1/reports/tasks-csv');

        $response->assertStatus(200);
        $content = $response->streamedContent();

        // The formula prefix '=' must be prepended with a single quote "'"
        $this->assertStringContainsString("'=SUM(1+1)", $content);
    }
}
