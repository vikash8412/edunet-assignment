<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Form extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = ['title', 'schema', 'status'];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Form $form) {
            $form->public_id ??= strtolower((string) Str::ulid());
        });
    }

    /** Who personally created this form — an author field, not an access-control one (see tenant_id). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(FormEvent::class);
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function publicUrl(): string
    {
        return route('fill.show', $this->public_id);
    }

    /**
     * Create a form attributed to $user, scoped to their tenant. The single
     * place tenant_id is ever assigned — never trust it from client input.
     */
    public static function createForTenant(User $user, array $attributes): self
    {
        // user_id/tenant_id aren't in $fillable (mass-assignment must never
        // accept a tenant from client input) — set them directly instead.
        $form = new self($attributes);
        $form->user_id = $user->id;
        $form->tenant_id = $user->tenantId();
        $form->save();

        return $form;
    }

    /**
     * Persist a new schema snapshot: updates the form's working copy and
     * appends an immutable version row. History is never rewritten —
     * rollbacks are recorded as new versions too.
     */
    public function saveSchemaVersion(
        array $schema,
        ?int $userId,
        string $source = 'builder',
        ?string $label = null,
    ): FormVersion {
        $this->schema = $schema;
        $this->title = $schema['title'] ?? $this->title;
        $this->current_version = $this->current_version + 1;
        $this->save();

        return $this->versions()->create([
            'version' => $this->current_version,
            'schema' => $schema,
            'created_by' => $userId,
            'source' => $source,
            'label' => $label,
            'created_at' => now(),
        ]);
    }
}
