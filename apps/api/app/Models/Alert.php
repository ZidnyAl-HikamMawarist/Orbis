<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([TenantScope::class])]
class Alert extends Model
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
        'task_id',
        'source',
        'dedupe_key',
        'severity',
        'status',
        'title',
        'message',
        'resolved_at',
        'acknowledged_at',
        'acknowledged_by_membership_id',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'acknowledged_at' => 'datetime',
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

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(CompanyUser::class, 'acknowledged_by_membership_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'acknowledged']);
    }
}
