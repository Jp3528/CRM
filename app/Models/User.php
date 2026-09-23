<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'team_id', 'status', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user')->withTimestamps();
    }

    /** @return HasMany<Company, $this> */
    public function ownedCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'owner_id');
    }

    /** @return HasMany<Contact, $this> */
    public function ownedContacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'owner_id');
    }

    /** @return HasMany<Lead, $this> */
    public function ownedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'owner_id');
    }

    /** @return HasMany<Opportunity, $this> */
    public function ownedOpportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'owner_id');
    }

    /** @return HasMany<Task, $this> */
    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    // -----------------------------------------------------------------
    // RBAC operativo (Fase 2). Lógica centralizada: no dispersar
    // comprobaciones de roles/permisos por controladores o vistas.
    // -----------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuperAdmin(): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(
                fn (Role $role) => $role->name === 'Superadministrador'
                    || $role->slug === 'superadministrador'
            );
        }

        return $this->roles()
            ->where('name', 'Superadministrador')
            ->orWhere('slug', 'superadministrador')
            ->exists();
    }

    public function hasRole(string $role): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(
                fn (Role $r) => $r->name === $role || $r->slug === $role
            );
        }

        return $this->roles()
            ->where('name', $role)
            ->orWhere('slug', $role)
            ->exists();
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(string $permission): bool
    {
        // Excepción centralizada: Superadministrador lo puede todo.
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->relationLoaded('permissions')) {
            if ($this->permissions->contains('name', $permission)) {
                return true;
            }
        } elseif ($this->permissions()->where('name', $permission)->exists()) {
            return true;
        }

        if ($this->relationLoaded('roles')) {
            foreach ($this->roles as $role) {
                if ($role->relationLoaded('permissions')) {
                    if ($role->permissions->contains('name', $permission)) {
                        return true;
                    }
                } elseif ($role->permissions()->where('name', $permission)->exists()) {
                    return true;
                }
            }

            return false;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('name', $permission))
            ->exists();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function primaryRole(): ?Role
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->first();
        }

        return $this->roles()->orderBy('roles.id')->first();
    }
}
