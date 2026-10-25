<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with deterministic multi-tenant test data.
     */
    public function run(): void
    {
        // 1. Create Companies
        $nusantara = Company::create([
            'id' => (string) Str::ulid(),
            'name' => 'Nusantara Retail Group',
            'slug' => 'nusantara',
            'timezone' => 'Asia/Jakarta',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $borneo = Company::create([
            'id' => (string) Str::ulid(),
            'name' => 'Borneo Logistics Corp',
            'slug' => 'borneo',
            'timezone' => 'Asia/Makassar',
            'status' => 'active',
            'created_at' => now(),
        ]);

        // 2. Create Users
        $defaultPassword = Hash::make('password');

        $adminNusantara = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Budi Santoso (Owner)',
            'email' => 'admin@nusantara.local',
            'password' => $defaultPassword,
            'status' => 'active',
            'email_verified_at' => now(),
            'created_at' => now(),
        ]);

        $managerNusantara = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Dewi Lestari (Manager)',
            'email' => 'manager@nusantara.local',
            'password' => $defaultPassword,
            'status' => 'active',
            'email_verified_at' => now(),
            'created_at' => now(),
        ]);

        $staffNusantara = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Eko Prasetyo (Staff)',
            'email' => 'staff@nusantara.local',
            'password' => $defaultPassword,
            'status' => 'active',
            'email_verified_at' => now(),
            'created_at' => now(),
        ]);

        $ownerBorneo = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Hassan Borneo (Owner)',
            'email' => 'owner@borneo.local',
            'password' => $defaultPassword,
            'status' => 'active',
            'email_verified_at' => now(),
            'created_at' => now(),
        ]);

        $sharedUser = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Siti Multi-Tenant',
            'email' => 'shared@orbis.local',
            'password' => $defaultPassword,
            'status' => 'active',
            'email_verified_at' => now(),
            'created_at' => now(),
        ]);

        // 3. Attach Memberships
        // Nusantara Memberships
        CompanyUser::create([
            'id' => (string) Str::ulid(),
            'company_id' => $nusantara->id,
            'user_id' => $adminNusantara->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
        ]);

        CompanyUser::create([
            'id' => (string) Str::ulid(),
            'company_id' => $nusantara->id,
            'user_id' => $managerNusantara->id,
            'role' => 'operations_manager',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
        ]);

        CompanyUser::create([
            'id' => (string) Str::ulid(),
            'company_id' => $nusantara->id,
            'user_id' => $staffNusantara->id,
            'role' => 'staff',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
        ]);

        // Borneo Membership
        CompanyUser::create([
            'id' => (string) Str::ulid(),
            'company_id' => $borneo->id,
            'user_id' => $ownerBorneo->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
        ]);

        // Shared User memberships (Staff in Nusantara, Manager in Borneo)
        CompanyUser::create([
            'id' => (string) Str::ulid(),
            'company_id' => $nusantara->id,
            'user_id' => $sharedUser->id,
            'role' => 'staff',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
        ]);

        CompanyUser::create([
            'id' => (string) Str::ulid(),
            'company_id' => $borneo->id,
            'user_id' => $sharedUser->id,
            'role' => 'operations_manager',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
        ]);
    }
}
