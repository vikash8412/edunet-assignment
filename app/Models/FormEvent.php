<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['view', 'start', 'step', 'submit'];

    protected $fillable = ['session_hash', 'type', 'step', 'created_at'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
