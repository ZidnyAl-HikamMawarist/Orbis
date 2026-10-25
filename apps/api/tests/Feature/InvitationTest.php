<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'id' => (string) Str::ulid(),
            'name' => 'Acme Logistics',
            'slug' => 'acme',
            'timezone' => 'Asia/Jakarta',
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Admin User',
            'email' => 'admin@acme.local',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        CompanyUser::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'role' => 'company_admin',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->staff = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Staff User',
            'email' => 'staff@acme.local',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        CompanyUser::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->company->id,
            'user_id' => $this->staff->id,
            'role' => 'staff',
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    public function test_admin_can_dispatch_invitation_with_sha256_hashed_token(): void
    {
        $this->actingAs($this->admin);
        session(['current_company_id' => $this->company->id]);

        $response = $this->postJson('/api/v1/invitations', [
            'email' => 'newhire@acme.local',
            'role' => 'operations_manager',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['invitation', 'invite_token', 'invite_url']);

        $plainToken = $response->json('invite_token');
        $expectedHash = hash('sha256', $plainToken);

        $this->assertDatabaseHas('company_invitations', [
            'company_id' => $this->company->id,
            'email' => 'newhire@acme.local',
            'token_hash' => $expectedHash,
            'status' => 'pending',
        ]);
    }

    public function test_staff_cannot_dispatch_invitations(): void
    {
        $this->actingAs($this->staff);
        session(['current_company_id' => $this->company->id]);

        $response = $this->postJson('/api/v1/invitations', [
            'email' => 'friend@acme.local',
            'role' => 'staff',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_cannot_escalate_role_to_owner(): void
    {
        $this->actingAs($this->admin);
        session(['current_company_id' => $this->company->id]);

        $response = $this->postJson('/api/v1/invitations', [
            'email' => 'boss@acme.local',
            'role' => 'owner',
        ]);

        $response->assertStatus(422); // 'owner' not in validation allowed invite roles
    }

    public function test_valid_token_can_be_publicly_validated(): void
    {
        $plainToken = 'test-token-12345678901234567890123456789012';
        $tokenHash = hash('sha256', $plainToken);

        CompanyInvitation::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->company->id,
            'email' => 'invited@acme.local',
            'role' => 'staff',
            'token_hash' => $tokenHash,
            'invited_by_membership_id' => $this->admin->memberships()->first()->id,
            'status' => 'pending',
            'expires_at' => now()->addHours(72),
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/invitations/validate/{$plainToken}");

        $response->assertStatus(200)
            ->assertJson([
                'valid' => true,
                'email' => 'invited@acme.local',
                'company_name' => 'Acme Logistics',
            ]);
    }

    public function test_expired_token_is_rejected(): void
    {
        $plainToken = 'expired-token-123456789012345678901234';
        $tokenHash = hash('sha256', $plainToken);

        CompanyInvitation::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->company->id,
            'email' => 'late@acme.local',
            'role' => 'staff',
            'token_hash' => $tokenHash,
            'invited_by_membership_id' => $this->admin->memberships()->first()->id,
            'status' => 'pending',
            'expires_at' => now()->subHour(), // Expired!
            'created_at' => now()->subDays(4),
        ]);

        $response = $this->getJson("/api/v1/invitations/validate/{$plainToken}");

        $response->assertStatus(410)
            ->assertJsonPath('code', 'INVITATION_EXPIRED');
    }

    public function test_invitation_acceptance_creates_user_and_active_membership(): void
    {
        $plainToken = 'accept-token-123456789012345678901234';
        $tokenHash = hash('sha256', $plainToken);

        CompanyInvitation::create([
            'id' => (string) Str::ulid(),
            'company_id' => $this->company->id,
            'email' => 'joiner@acme.local',
            'role' => 'operations_manager',
            'token_hash' => $tokenHash,
            'invited_by_membership_id' => $this->admin->memberships()->first()->id,
            'status' => 'pending',
            'expires_at' => now()->addHours(72),
            'created_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/invitations/{$plainToken}/accept", [
            'name' => 'New Joiner',
            'password' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'joiner@acme.local')
            ->assertJsonPath('membership.role', 'operations_manager');

        $this->assertDatabaseHas('users', ['email' => 'joiner@acme.local']);
        $this->assertDatabaseHas('company_user', [
            'company_id' => $this->company->id,
            'role' => 'operations_manager',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('company_invitations', [
            'token_hash' => $tokenHash,
            'status' => 'accepted',
        ]);
    }
}
