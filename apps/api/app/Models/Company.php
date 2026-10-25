<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory, HasUlids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'timezone',
        'status',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyUser::class, 'company_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(CompanyInvitation::class, 'company_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'company_id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'company_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'company_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'company_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'company_id');
    }

    public function kpiDefinitions(): HasMany
    {
        return $this->hasMany(KpiDefinition::class, 'company_id');
    }

    public function worldConfigs(): HasMany
    {
        return $this->hasMany(WorldConfig::class, 'company_id');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class, 'company_id');
    }
}
