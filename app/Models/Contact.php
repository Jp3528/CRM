<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory, SoftDeletes;

    public const STATUSES = ['active', 'inactive'];

    public const SORTABLE = ['first_name', 'status', 'created_at'];

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'mobile',
        'job_title', 'department', 'company_id', 'owner_id', 'status', 'notes',
    ];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<Opportunity, $this> */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    /** @return HasMany<Quote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class)->latest();
    }

    /** @return MorphToMany<Tag, $this> */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /** @return MorphMany<Activity, $this> */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subjectable');
    }

    /** @return MorphMany<Task, $this> */
    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Contact>  $query */
    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        $term = mb_strtolower(trim($term));

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(first_name) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(email) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(mobile) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(job_title) LIKE ?', ["%{$term}%"])
                ->orWhereHas('company', function ($cq) use ($term) {
                    $cq->whereRaw('LOWER(trade_name) LIKE ?', ["%{$term}%"]);
                });
        });
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Contact>  $query */
    public function scopeStatus($query, ?string $status)
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('contacts.status', $status);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Contact>  $query */
    public function scopeForCompany($query, mixed $companyId)
    {
        if (blank($companyId)) {
            return $query;
        }

        return $query->where('contacts.company_id', $companyId);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Contact>  $query */
    public function scopeDepartment($query, ?string $department)
    {
        if (blank($department)) {
            return $query;
        }

        return $query->where('contacts.department', $department);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Contact>  $query */
    public function scopeOwnedBy($query, mixed $ownerId)
    {
        if (blank($ownerId)) {
            return $query;
        }

        return $query->where('contacts.owner_id', $ownerId);
    }
}
