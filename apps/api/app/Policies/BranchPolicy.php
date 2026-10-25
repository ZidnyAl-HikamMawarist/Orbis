<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    private function membership(User $user, Branch $branch)
    {
        return $user->membershipIn($branch->company_id);
    }

    public function viewAny(User $user): bool
    {
        $companyId = session('current_company_id');
        return $companyId && $user->membershipIn($companyId) !== null;
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->membership($user, $branch) !== null;
    }

    public function create(User $user): bool
    {
        $companyId = session('current_company_id');
        $membership = $companyId ? $user->membershipIn($companyId) : null;
        return $membership?->canManageOrganization() ?? false;
    }

    public function update(User $user, Branch $branch): bool
    {
        $membership = $this->membership($user, $branch);
        return $membership?->canManageOrganization() ?? false;
    }

    public function archive(User $user, Branch $branch): bool
    {
        $membership = $this->membership($user, $branch);
        return $membership?->canManageOrganization() ?? false;
    }
}
