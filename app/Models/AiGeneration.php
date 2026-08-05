<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGeneration extends Model
{
    use BelongsToTenant;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'form_id', 'mode', 'prompt', 'status', 'model', 'prompt_tokens',
        'completion_tokens', 'latency_ms', 'attempts', 'error',
        'result_schema', 'warnings',
    ];

    protected function casts(): array
    {
        return [
            'result_schema' => 'array',
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
     * Queue a generation attributed to $user, scoped to their tenant. The
     * single place tenant_id is ever assigned.
     */
    public static function createForTenant(User $user, array $attributes): self
    {
        $generation = new self($attributes);
        $generation->user_id = $user->id;
        $generation->tenant_id = $user->tenantId();
        $generation->save();

        return $generation;
    }
}
