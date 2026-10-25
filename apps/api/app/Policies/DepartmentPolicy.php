<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    private function membership(User $user, Department $department)
    {
        return $user->membershipIn($department->company_id);
    }

    public function viewAny(User $user): bool
    {
        $companyId = session('current_company_id');
        return $companyId && $user->membershipIn($companyId) !== null;
    }

    public function view(User $user, Department $department): bool
    {
        return $this->membership($user, $department) !== null;
    }

    public function create(User $user): bool
    {
        $companyId = session('current_company_id');
        $membership = $companyId ? $user->membershipIn($companyId) : null;
        return $membership?->canManageOrganization() ?? false;
    }

    public function update(User $user, Department $department): bool
    {
        $membership = $this->membership($user, $department);
        return $membership?->canManageOrganization() ?? false;
    }

    public function archive(User $user, Department $department): bool
    {
        $membership = $this->membership($user, $department);
        return $membership?->canManageOrganization() ?? false;
    }
}
