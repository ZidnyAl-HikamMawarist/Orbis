<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    private function membership(User $user, Project $project)
    {
        return $user->membershipIn($project->company_id);
    }

    public function viewAny(User $user): bool
    {
        $companyId = session('current_company_id');
        return $companyId && $user->membershipIn($companyId) !== null;
    }

    public function view(User $user, Project $project): bool
    {
        return $this->membership($user, $project) !== null;
    }

    public function create(User $user): bool
    {
        $companyId = session('current_company_id');
        $membership = $companyId ? $user->membershipIn($companyId) : null;
        return $membership?->canManageProjects() ?? false;
    }

    public function update(User $user, Project $project): bool
    {
        $membership = $this->membership($user, $project);
        return $membership?->canManageProjects() ?? false;
    }

    public function archive(User $user, Project $project): bool
    {
        $membership = $this->membership($user, $project);
        return $membership?->isManager() ?? false;
    }
}
