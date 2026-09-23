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
}
