<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Import extends Model
{
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
}
