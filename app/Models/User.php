<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPER = 'super';
    public const ROLE_TENANT = 'tenant';
    public const ROLE_USER = 'user';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'tenant_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'disabled_at' => 'datetime',
        ];
    }

    /** Records this user personally created (author, not necessarily current owner — see Form::tenant_id). */
    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    public function aiGenerations(): HasMany
    {
        return $this->hasMany(AiGeneration::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }

    /** The company (tenant-role user) this account belongs to, if role=user. */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /** This tenant's team members, if role=tenant. */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    public function isSuper(): bool
    {
        return $this->role === self::ROLE_SUPER;
    }

    public function isTenant(): bool
    {
        return $this->role === self::ROLE_TENANT;
    }

    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    /**
     * The id of the tenant (company) this user's data belongs to.
     * - tenant role: their own id (they ARE the tenant)
     * - user role: their tenant_id column
     * - super role: null — supers own no data
     */
    public function tenantId(): ?int
    {
        return match ($this->role) {
            self::ROLE_TENANT => $this->id,
            self::ROLE_USER => $this->tenant_id,
            default => null,
        };
    }

    /**
     * True if this account's company has been suspended by a super admin.
     * A `user` inherits their tenant-owner's disabled_at; a `tenant` checks
     * their own; a `super` is never disabled.
     */
    public function isDisabled(): bool
    {
        return match ($this->role) {
            self::ROLE_TENANT => $this->disabled_at !== null,
            self::ROLE_USER => $this->tenant?->disabled_at !== null,
            default => false,
        };
    }
}
