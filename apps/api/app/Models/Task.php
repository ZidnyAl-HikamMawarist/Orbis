<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[ScopedBy([TenantScope::class])]
class Task extends Model
{
    use HasFactory, HasUlids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'branch_id',
        'department_id',
        'project_id',
        'creator_membership_id',
        'assignee_membership_id',
        'code',
        'title',
        'description',
        'priority',
        'status',
        'due_at',
        'completed_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(CompanyUser::class, 'creator_membership_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(CompanyUser::class, 'assignee_membership_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(TaskStatusHistory::class, 'task_id')->orderBy('created_at', 'asc');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'task_id')->orderBy('created_at', 'asc');
    }

    public function isOverdue(): bool
    {
        if (! $this->due_at) {
            return false;
        }

        return ! in_array($this->status, ['done', 'cancelled'], true) && $this->due_at->isPast();
    }

    /**
     * Atomically transition task status with append-only history and project progress synchronization.
     */
    public function transitionStatus(string $newStatus, string $actorMembershipId, ?string $note = null): self
    {
        return DB::transaction(function () use ($newStatus, $actorMembershipId, $note) {
            $fromStatus = $this->status;

            if ($fromStatus === $newStatus) {
                return $this;
            }

            $completedAt = $newStatus === 'done' ? now() : ($fromStatus === 'done' ? null : $this->completed_at);

            $this->update([
                'status' => $newStatus,
                'completed_at' => $completedAt,
            ]);

            TaskStatusHistory::create([
                'id' => (string) Str::ulid(),
                'company_id' => $this->company_id,
                'task_id' => $this->id,
                'actor_membership_id' => $actorMembershipId,
                'from_status' => $fromStatus,
                'to_status' => $newStatus,
                'note' => $note,
                'created_at' => now(),
            ]);

            if ($this->project_id) {
                $this->project?->recalculateProgress();
            }

            AuditEvent::log('task.status_transition', 'task', $this->id, [
                'from' => $fromStatus,
                'to' => $newStatus,
                'actor_membership_id' => $actorMembershipId,
            ]);

            return $this->fresh();
        });
    }
}
