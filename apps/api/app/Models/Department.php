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
class Department extends Model
{
    use HasFactory, HasUlids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'branch_id',
        'code',
        'name',
        'status',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
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

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'department_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'department_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'department_id');
    }

    public function worldConfig()
    {
        return $this->hasOne(WorldConfig::class, 'department_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->whereNull('archived_at');
    }
}
