<?php

namespace App\Policies;

use App\Models\CompanyUser;
use App\Models\User;

class CompanyUserPolicy
{
    private function membership(User $user, ?string $companyId = null): ?CompanyUser
    {
        $targetCompanyId = $companyId ?? session('current_company_id');
        return $targetCompanyId ? $user->membershipIn($targetCompanyId) : null;
    }

    public function viewAny(User $user): bool
    {
        return $this->membership($user) !== null;
    }

    public function view(User $user, CompanyUser $target): bool
    {
        return $this->membership($user, $target->company_id) !== null;
    }

    public function updateRole(User $user, CompanyUser $target): bool
    {
        $currentMembership = $this->membership($user, $target->company_id);
        if (! $currentMembership || ! $currentMembership->canManageMembers()) {
            return false;
        }

        // Cannot change own role
        if ($currentMembership->id === $target->id) {
            return false;
        }

        // Only owner can demote/promote to owner
        if ($target->role === 'owner' && ! $currentMembership->isOwner()) {
            return false;
        }

        return true;
    }

    public function deactivate(User $user, CompanyUser $target): bool
    {
        $currentMembership = $this->membership($user, $target->company_id);
        if (! $currentMembership || ! $currentMembership->canManageMembers()) {
            return false;
        }

        // Cannot deactivate yourself
        if ($currentMembership->id === $target->id) {
            return false;
        }

        // Cannot deactivate owner unless you are owner
        if ($target->isOwner()) {
            return false;
        }

        return true;
    }
}
