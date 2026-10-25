<?php

namespace App\Policies;

use App\Models\CompanyUser;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
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

    public function view(User $user, Task $task): bool
    {
        $membership = $this->membership($user, $task->company_id);
        return $membership !== null;
    }

    public function create(User $user): bool
    {
        $membership = $this->membership($user);
        if (! $membership) {
            return false;
        }

        // Viewers cannot create tasks; members and higher can
        return $membership->role !== 'viewer';
    }

    public function update(User $user, Task $task): bool
    {
        $membership = $this->membership($user, $task->company_id);
        if (! $membership) {
            return false;
        }

        // Managers and leaders can update any task
        if ($membership->canManageProjects()) {
            return true;
        }

        // Assignee or creator can update their own task
        return $task->assignee_membership_id === $membership->id || $task->creator_membership_id === $membership->id;
    }

    public function transition(User $user, Task $task, string $newStatus): bool
    {
        $membership = $this->membership($user, $task->company_id);
        if (! $membership) {
            return false;
        }

        // If reopening a completed task (from 'done' to anything else), check canReopenTask
        if ($task->status === 'done' && $newStatus !== 'done') {
            return $membership->canReopenTask();
        }

        // Managers, leaders, assignees, or creators can transition
        if ($membership->canManageProjects()) {
            return true;
        }

        return $task->assignee_membership_id === $membership->id || $task->creator_membership_id === $membership->id;
    }

    public function delete(User $user, Task $task): bool
    {
        $membership = $this->membership($user, $task->company_id);
        if (! $membership) {
            return false;
        }

        return $membership->canManageProjects();
    }
}
