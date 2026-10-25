<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    private User $adminUser;
    private User $staffUser;
    private Company $companyA;
    private Company $companyB;
    private Branch $companyBBranch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'id' => (string) Str::ulid(),
            'name' => 'Org Test Company A',
            'slug' => 'org-test-a-' . Str::random(5),
            'status' => 'active',
        ]);

        $this->companyB = Company::create([
            'id' => (string) Str::ulid(),
            'name' => 'Org Test Company B',
            'slug' => 'org-test-b-' . Str::random(5),
            'status' => 'active',
        ]);

        $this->companyBBranch = Branch::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyB->id,
            'code' => 'B-HQ',
            'name' => 'Company B Branch',
            'status' => 'active',
        ]);

        $this->adminUser = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Org Admin',
            'email' => 'org_admin_' . Str::random(6) . '@example.com',
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
            'name' => 'Org Staff',
            'email' => 'org_staff_' . Str::random(6) . '@example.com',
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

    public function test_admin_can_create_and_list_branches(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->postJson('/api/v1/branches', [
                'code' => 'jkt-01',
                'name' => 'Jakarta Central Branch',
                'address' => 'Jl. Sudirman No 1',
                'timezone' => 'Asia/Jakarta',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('branch.code', 'JKT-01')
            ->assertJsonPath('branch.name', 'Jakarta Central Branch');

        $list = $this->actingAs($this->adminUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->getJson('/api/v1/branches');

        $list->assertStatus(200)
            ->assertJsonPath('data.0.code', 'JKT-01');
    }

    public function test_staff_cannot_create_branch(): void
    {
        $response = $this->actingAs($this->staffUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->postJson('/api/v1/branches', [
                'code' => 'sub-01',
                'name' => 'Surabaya Branch',
            ]);

        $response->assertStatus(403);
    }

    public function test_branch_code_must_be_unique_within_company(): void
    {
        Branch::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyA->id,
            'code' => 'BDG-01',
            'name' => 'Bandung Branch',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->postJson('/api/v1/branches', [
                'code' => 'bdg-01',
                'name' => 'Duplicate Bandung',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_admin_can_create_department_under_branch(): void
    {
        $branch = Branch::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyA->id,
            'code' => 'DPS-01',
            'name' => 'Denpasar Branch',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->postJson('/api/v1/departments', [
                'branch_id' => $branch->id,
                'code' => 'eng',
                'name' => 'Engineering',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('department.code', 'ENG')
            ->assertJsonPath('department.branch_id', $branch->id);
    }

    public function test_department_creation_with_foreign_branch_id_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->postJson('/api/v1/departments', [
                'branch_id' => $this->companyBBranch->id,
                'code' => 'sec-01',
                'name' => 'Security Dept',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_admin_can_update_member_role(): void
    {
        $staffMembership = CompanyUser::where('company_id', $this->companyA->id)
            ->where('user_id', $this->staffUser->id)
            ->firstOrFail();

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->putJson("/api/v1/members/{$staffMembership->id}/role", [
                'role' => 'operations_manager',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('membership.role', 'operations_manager');
    }
}
