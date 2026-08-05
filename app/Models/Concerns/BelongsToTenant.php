<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For models with a tenant_id column (Form, AiGeneration, Import).
 * Centralizes the "which tenant does this row belong to" query shape so
 * every controller/policy uses the same idiom instead of repeating a raw
 * where() clause.
 */
trait BelongsToTenant
{
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where($query->getModel()->getTable().'.tenant_id', $tenantId);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}
