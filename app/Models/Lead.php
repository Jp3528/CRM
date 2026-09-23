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
}
