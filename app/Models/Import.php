<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Import extends Model
{
    use BelongsToTenant;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_PARSING = 'parsing';
    public const STATUS_READY = 'ready';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'form_id', 'original_name', 'path', 'kind', 'status',
        'parsed_schema', 'warnings', 'error',
    ];

    protected function casts(): array
    {
        return [
            'parsed_schema' => 'array',
            'warnings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Create an import attributed to $user, scoped to their tenant. The
     * single place tenant_id is ever assigned.
     */
    public static function createForTenant(User $user, array $attributes): self
    {
        $import = new self($attributes);
        $import->user_id = $user->id;
        $import->tenant_id = $user->tenantId();
        $import->save();

        return $import;
    }
}
