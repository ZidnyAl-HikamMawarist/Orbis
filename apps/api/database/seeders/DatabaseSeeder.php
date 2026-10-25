<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with deterministic multi-tenant test data.
     * Uses firstOrCreate to ensure idempotent, repeatable execution.
     */
    public function run(): void
    {
        // 1. Companies
        $nusantara = Company::firstOrCreate(
            ['slug' => 'nusantara'],
            [
                'id' => (string) Str::ulid(),
                'name' => 'Nusantara Retail Group',
                'timezone' => 'Asia/Jakarta',
                'status' => 'active',
                'created_at' => now(),
            ]
        );

        $borneo = Company::firstOrCreate(
            ['slug' => 'borneo'],
            [
                'id' => (string) Str::ulid(),
                'name' => 'Borneo Logistics Corp',
                'timezone' => 'Asia/Makassar',
                'status' => 'active',
                'created_at' => now(),
            ]
        );

        // 2. Users
        $defaultPassword = Hash::make('password');

        $adminNusantara = User::firstOrCreate(
            ['email' => 'admin@nusantara.local'],
            [
                'id' => (string) Str::ulid(),
                'name' => 'Budi Santoso (Owner)',
                'password' => $defaultPassword,
                'status' => 'active',
                'email_verified_at' => now(),
                'created_at' => now(),
            ]
        );

        $managerNusantara = User::firstOrCreate(
            ['email' => 'manager@nusantara.local'],
            [
                'id' => (string) Str::ulid(),
                'name' => 'Dewi Lestari (Manager)',
                'password' => $defaultPassword,
                'status' => 'active',
                'email_verified_at' => now(),
                'created_at' => now(),
            ]
        );

        $staffNusantara = User::firstOrCreate(
            ['email' => 'staff@nusantara.local'],
            [
                'id' => (string) Str::ulid(),
                'name' => 'Eko Prasetyo (Staff)',
                'password' => $defaultPassword,
                'status' => 'active',
                'email_verified_at' => now(),
                'created_at' => now(),
            ]
        );

        $ownerBorneo = User::firstOrCreate(
            ['email' => 'owner@borneo.local'],
            [
                'id' => (string) Str::ulid(),
                'name' => 'Hassan Borneo (Owner)',
                'password' => $defaultPassword,
                'status' => 'active',
                'email_verified_at' => now(),
                'created_at' => now(),
            ]
        );

        $sharedUser = User::firstOrCreate(
            ['email' => 'shared@orbis.local'],
            [
                'id' => (string) Str::ulid(),
                'name' => 'Siti Multi-Tenant',
                'password' => $defaultPassword,
                'status' => 'active',
                'email_verified_at' => now(),
                'created_at' => now(),
            ]
        );

        // 3. Attach Memberships
        CompanyUser::firstOrCreate(
            ['company_id' => $nusantara->id, 'user_id' => $adminNusantara->id],
            [
                'id' => (string) Str::ulid(),
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
            ]
        );

        CompanyUser::firstOrCreate(
            ['company_id' => $nusantara->id, 'user_id' => $managerNusantara->id],
            [
                'id' => (string) Str::ulid(),
                'role' => 'operations_manager',
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
            ]
        );

        CompanyUser::firstOrCreate(
            ['company_id' => $nusantara->id, 'user_id' => $staffNusantara->id],
            [
                'id' => (string) Str::ulid(),
                'role' => 'staff',
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
            ]
        );

        CompanyUser::firstOrCreate(
            ['company_id' => $borneo->id, 'user_id' => $ownerBorneo->id],
            [
                'id' => (string) Str::ulid(),
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
            ]
        );

        CompanyUser::firstOrCreate(
            ['company_id' => $nusantara->id, 'user_id' => $sharedUser->id],
            [
                'id' => (string) Str::ulid(),
                'role' => 'staff',
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
            ]
        );

        CompanyUser::firstOrCreate(
            ['company_id' => $borneo->id, 'user_id' => $sharedUser->id],
            [
                'id' => (string) Str::ulid(),
                'role' => 'operations_manager',
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
            ]
        );

        // 4. Seed Borneo isolated branch and task for multi-tenant validation
        $borneoBranch = Branch::firstOrCreate(
            ['company_id' => $borneo->id, 'code' => 'BR-BPN'],
            [
                'id' => (string) Str::ulid(),
                'name' => 'Balikpapan Port Facility',
                'status' => 'active',
                'created_at' => now(),
            ]
        );

        $borneoDept = Department::firstOrCreate(
            ['company_id' => $borneo->id, 'code' => 'DEPT-LOG'],
            [
                'id' => (string) Str::ulid(),
                'branch_id' => $borneoBranch->id,
                'name' => 'Maritime Logistics',
                'status' => 'active',
                'created_at' => now(),
            ]
        );

        $borneoOwnerMembership = CompanyUser::where('company_id', $borneo->id)
            ->where('user_id', $ownerBorneo->id)
            ->first();

        Task::firstOrCreate(
            ['company_id' => $borneo->id, 'code' => 'TSK-BORNEO-01'],
            [
                'id' => (string) Str::ulid(),
                'branch_id' => $borneoBranch->id,
                'department_id' => $borneoDept->id,
                'creator_membership_id' => $borneoOwnerMembership->id,
                'title' => 'Confidential Borneo Freight Manifest',
                'status' => 'in_progress',
                'priority' => 'high',
                'created_at' => now(),
            ]
        );
    }
}
