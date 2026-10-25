<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use RuntimeException;

class AuditEvent extends Model
{
    use HasUlids;

    public $timestamps = false;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'actor_membership_id',
        'action',
        'resource_type',
        'resource_id',
        'request_id',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(CompanyUser::class, 'actor_membership_id');
    }

    /**
     * Prevent updates to preserve tamper evidence.
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new RuntimeException('Audit events are immutable and cannot be updated.');
    }

    /**
     * Prevent deletion from standard application context.
     */
    public function delete(): ?bool
    {
        throw new RuntimeException('Audit events cannot be deleted from the application tier.');
    }

    public static function log(string $action, string $resourceType, ?string $resourceId = null, ?array $metadata = null): ?self
    {
        $companyId = session('current_company_id');
        if (! $companyId) {
            return null;
        }

        $user = auth()->user();
        $membership = $user ? $user->membershipIn($companyId) : null;

        return static::create([
            'id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'actor_membership_id' => $membership?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'request_id' => request()->header('X-Request-ID') ?? (string) Str::uuid(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
