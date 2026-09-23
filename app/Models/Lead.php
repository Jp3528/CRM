<?php

namespace App\Models;

use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name', 'last_name', 'company_name', 'email', 'phone',
        'source', 'status', 'score', 'owner_id', 'estimated_value', 'notes',
        'converted_at', 'converted_contact_id', 'converted_company_id',
    ];

    public const STATUSES = ['new', 'contacted', 'qualified', 'unqualified', 'converted'];

    /** Estados asignables manualmente (converted solo vía flujo de conversión). */
    public const EDITABLE_STATUSES = ['new', 'contacted', 'qualified', 'unqualified'];

    public const SOURCES = ['website', 'referral', 'campaign', 'social', 'email', 'phone', 'event', 'other'];

    public const SORTABLE = ['first_name', 'status', 'score', 'estimated_value', 'created_at'];

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'converted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<Contact, $this> */
    public function convertedContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'converted_contact_id');
    }

    /** @return BelongsTo<Company, $this> */
    public function convertedCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'converted_company_id');
    }

    /** @return HasMany<Opportunity, $this> */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
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

    public function isConverted(): bool
    {
        return $this->status === 'converted' || $this->converted_at !== null;
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Lead>  $query */
    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        $term = mb_strtolower(trim($term));

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(first_name) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(company_name) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(email) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$term}%"]);
        });
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Lead>  $query */
    public function scopeStatus($query, ?string $status)
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('leads.status', $status);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Lead>  $query */
    public function scopeSource($query, ?string $source)
    {
        if (blank($source)) {
            return $query;
        }

        return $query->where('leads.source', $source);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Lead>  $query */
    public function scopeOwnedBy($query, mixed $ownerId)
    {
        if (blank($ownerId)) {
            return $query;
        }

        return $query->where('leads.owner_id', $ownerId);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Lead>  $query */
    public function scopeScoreBetween($query, mixed $min, mixed $max)
    {
        if (! blank($min)) {
            $query->where('leads.score', '>=', (int) $min);
        }

        if (! blank($max)) {
            $query->where('leads.score', '<=', (int) $max);
        }

        return $query;
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Lead>  $query */
    public function scopeConverted($query, ?string $value)
    {
        if ($value === 'yes') {
            return $query->whereNotNull('leads.converted_at');
        }

        if ($value === 'no') {
            return $query->whereNull('leads.converted_at');
        }

        return $query;
    }
}
