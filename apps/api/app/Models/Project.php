<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([TenantScope::class])]
class Project extends Model
{
    use HasFactory, HasUlids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'branch_id',
        'department_id',
        'lead_membership_id',
        'code',
        'name',
        'description',
        'status',
        'progress_mode',
        'progress_percent',
        'start_date',
        'due_date',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'integer',
            'start_date' => 'date',
            'due_date' => 'date',
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

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CompanyUser::class, 'lead_membership_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    /**
     * Recalculate project progress percentage strictly using task-derived status.
     * Backlog is included in the denominator to avoid false 100% completions (OD-003, KPI_SEMANTICS_MATRIX.md).
     */
    public function recalculateProgress(): int
    {
        $eligibleTasks = $this->tasks()
            ->whereNull('archived_at')
            ->whereIn('status', ['backlog', 'todo', 'in_progress', 'blocked', 'done'])
            ->get(['status']);

        $totalEligible = $eligibleTasks->count();

        if ($totalEligible === 0) {
            $this->update(['progress_percent' => 0]);
            return 0;
        }

        $doneCount = $eligibleTasks->where('status', 'done')->count();
        $progress = (int) round(($doneCount / $totalEligible) * 100);

        $this->update(['progress_percent' => $progress]);
        return $progress;
    }
}
