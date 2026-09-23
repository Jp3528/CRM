<?php

namespace App\Models;

use App\Support\DataScope;
use Database\Factories\OpportunityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Opportunity extends Model
{
    /** @use HasFactory<OpportunityFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'description', 'amount', 'currency', 'probability',
        'expected_close_date', 'actual_close_date', 'status', 'loss_reason',
        'owner_id', 'pipeline_id', 'pipeline_stage_id',
        'company_id', 'contact_id', 'lead_id',
    ];

    public const STATUSES = ['open', 'won', 'lost'];

    public const CURRENCIES = ['USD', 'COP', 'EUR', 'MXN'];

    public const DEFAULT_CURRENCY = 'USD';

    public const SORTABLE = ['name', 'amount', 'probability', 'expected_close_date', 'created_at', 'status'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expected_close_date' => 'date',
            'actual_close_date' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<Pipeline, $this> */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /** @return BelongsTo<PipelineStage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return HasMany<OpportunityStageHistory, $this> */
    public function stageHistory(): HasMany
    {
        return $this->hasMany(OpportunityStageHistory::class)->orderBy('changed_at');
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

    public function getWeightedAmountAttribute(): ?string
    {
        if ($this->amount === null || $this->probability === null) {
            return null;
        }

        return bcmul((string) $this->amount, bcdiv((string) $this->probability, '100', 6), 2);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['won', 'lost'], true);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Opportunity>  $query */
    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        $term = mb_strtolower(trim($term));

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(opportunities.name) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(opportunities.description) LIKE ?', ["%{$term}%"])
                ->orWhereHas('company', function ($cq) use ($term) {
                    $cq->whereRaw('LOWER(trade_name) LIKE ?', ["%{$term}%"])
                        ->orWhereRaw('LOWER(legal_name) LIKE ?', ["%{$term}%"]);
                })
                ->orWhereHas('contact', function ($cq) use ($term) {
                    $cq->whereRaw('LOWER(first_name) LIKE ?', ["%{$term}%"])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$term}%"]);
                });
        });
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Opportunity>  $query */
    public function scopeStatus($query, ?string $status)
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('opportunities.status', $status);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Opportunity>  $query */
    public function scopePipeline($query, mixed $pipelineId)
    {
        if (blank($pipelineId)) {
            return $query;
        }

        return $query->where('opportunities.pipeline_id', $pipelineId);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Opportunity>  $query */
    public function scopeStage($query, mixed $stageId)
    {
        if (blank($stageId)) {
            return $query;
        }

        return $query->where('opportunities.pipeline_stage_id', $stageId);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Opportunity>  $query */
    public function scopeOwnedBy($query, mixed $ownerId)
    {
        if (blank($ownerId)) {
            return $query;
        }

        return $query->where('opportunities.owner_id', $ownerId);
    }

    /**
     * Alcance de datos (Fase 9.5): filtra por propietario/equipo según rol.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Opportunity>  $query
     */
    public function scopeVisibleTo($query, User $user)
    {
        return DataScope::scopeOwned($query, $user);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Opportunity>  $query */
    public function scopeForCompany($query, mixed $companyId)
    {
        if (blank($companyId)) {
            return $query;
        }

        return $query->where('opportunities.company_id', $companyId);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Opportunity>  $query */
    public function scopeAmountBetween($query, mixed $min, mixed $max)
    {
        if (! blank($min)) {
            $query->where('opportunities.amount', '>=', $min);
        }

        if (! blank($max)) {
            $query->where('opportunities.amount', '<=', $max);
        }

        return $query;
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Opportunity>  $query */
    public function scopeCloseBetween($query, mixed $from, mixed $to)
    {
        if (! blank($from)) {
            $query->whereDate('opportunities.expected_close_date', '>=', $from);
        }

        if (! blank($to)) {
            $query->whereDate('opportunities.expected_close_date', '<=', $to);
        }

        return $query;
    }
}
