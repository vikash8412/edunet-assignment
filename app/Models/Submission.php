<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Submission extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'form_version_id', 'data', 'search_text', 'ip_hash',
        'user_agent', 'started_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'started_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'form_version_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(SubmissionFile::class);
    }
}
