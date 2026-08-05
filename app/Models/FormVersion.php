<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['version', 'schema', 'created_by', 'source', 'label', 'created_at'];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
