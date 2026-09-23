<?php

namespace App\Models;

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
