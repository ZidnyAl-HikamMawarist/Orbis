<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyUser extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'company_user';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'user_id',
        'role',
        'status',
        'invited_by_membership_id',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(CompanyUser::class, 'invited_by_membership_id');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_membership_id');
    }

    // Role helpers
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['owner', 'company_admin'], true);
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['owner', 'company_admin', 'operations_manager', 'branch_manager'], true);
    }

    public function canManageCompany(): bool
    {
        return $this->isAdmin();
    }

    public function canManageMembers(): bool
    {
        return $this->isAdmin();
    }

    public function canManageOrganization(): bool
    {
        return in_array($this->role, ['owner', 'company_admin', 'operations_manager'], true);
    }

    public function canManageProjects(): bool
    {
        return in_array($this->role, ['owner', 'company_admin', 'operations_manager', 'branch_manager', 'department_lead'], true);
    }

    public function canReopenTask(): bool
    {
        return in_array($this->role, ['owner', 'company_admin', 'operations_manager', 'branch_manager'], true);
    }
}
