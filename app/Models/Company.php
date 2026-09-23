<?php

namespace App\Models;

use App\Support\DataScope;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, SoftDeletes;

    public const STATUSES = ['active', 'inactive'];

    public const SORTABLE = ['trade_name', 'status', 'created_at'];

    protected $fillable = [
        'trade_name', 'legal_name', 'tax_id', 'email', 'phone', 'website',
        'industry', 'company_size', 'address', 'city', 'region', 'country',
        'postal_code', 'status', 'owner_id', 'notes',
    ];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<Contact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
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

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class)->latest();
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest();
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class)->latest();
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

    /** @param  \Illuminate\Database\Eloquent\Builder<Company>  $query */
    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        $term = mb_strtolower(trim($term));

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(trade_name) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(legal_name) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(email) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(tax_id) LIKE ?', ["%{$term}%"]);
        });
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Company>  $query */
    public function scopeStatus($query, ?string $status)
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Company>  $query */
    public function scopeIndustry($query, ?string $industry)
    {
        if (blank($industry)) {
            return $query;
        }

        return $query->where('industry', $industry);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Company>  $query */
    public function scopeCountry($query, ?string $country)
    {
        if (blank($country)) {
            return $query;
        }

        return $query->where('country', $country);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Company>  $query */
    public function scopeOwnedBy($query, mixed $ownerId)
    {
        if (blank($ownerId)) {
            return $query;
        }

        return $query->where('owner_id', $ownerId);
    }

    /**
     * Alcance de datos (Fase 9.5): filtra por propietario/equipo según rol.
     * Sin N+1 ni filtrado en PHP: todo en SQL.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Company>  $query
     */
    public function scopeVisibleTo($query, User $user)
    {
        return DataScope::scopeOwned($query, $user);
    }
}
