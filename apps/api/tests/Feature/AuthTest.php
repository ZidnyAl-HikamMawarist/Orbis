<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private Company $companyA;
    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'id' => (string) Str::ulid(),
            'name' => 'Company Alpha',
            'slug' => 'alpha',
            'timezone' => 'Asia/Jakarta',
            'status' => 'active',
        ]);

        $this->companyB = Company::create([
            'id' => (string) Str::ulid(),
            'name' => 'Company Beta',
            'slug' => 'beta',
            'timezone' => 'Asia/Makassar',
            'status' => 'active',
        ]);

        $this->userA = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Alice Admin',
            'email' => 'alice@alpha.local',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        CompanyUser::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyA->id,
            'user_id' => $this->userA->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_successful_login_initializes_session_and_company_context(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'alice@alpha.local',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'alice@alpha.local')
            ->assertJsonPath('current_company.id', $this->companyA->id)
            ->assertJsonPath('current_membership.role', 'owner');

        $this->assertEquals($this->companyA->id, session('current_company_id'));
    }

    public function test_login_with_wrong_password_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'alice@alpha.local',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_retrieve_me_endpoint(): void
    {
        $this->actingAs($this->userA);
        session(['current_company_id' => $this->companyA->id]);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('user.id', $this->userA->id)
            ->assertJsonPath('current_company.name', 'Company Alpha');
    }

    public function test_user_can_switch_company_if_holding_active_membership(): void
    {
        // Add userA to companyB as well
        CompanyUser::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->companyB->id,
            'user_id' => $this->userA->id,
            'role' => 'staff',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($this->userA);
        session(['current_company_id' => $this->companyA->id]);

        $response = $this->postJson('/api/v1/auth/switch-company', [
            'company_id' => $this->companyB->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('current_company.id', $this->companyB->id)
            ->assertJsonPath('current_membership.role', 'staff');

        $this->assertEquals($this->companyB->id, session('current_company_id'));
    }

    public function test_user_cannot_switch_to_company_without_membership(): void
    {
        $this->actingAs($this->userA);
        session(['current_company_id' => $this->companyA->id]);

        // Attempt to switch to companyB where userA is NOT a member
        $response = $this->postJson('/api/v1/auth/switch-company', [
            'company_id' => $this->companyB->id,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('code', 'MEMBERSHIP_NOT_FOUND');

        // Verify session was NOT altered
        $this->assertEquals($this->companyA->id, session('current_company_id'));
    }

    public function test_logout_clears_session(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'alice@alpha.local',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/v1/auth/logout');
        $response->assertStatus(200);

        // Verify session was terminated by asserting protected me endpoint returns 401
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }
}
