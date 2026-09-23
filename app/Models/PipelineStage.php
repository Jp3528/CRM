<?php

namespace App\Models;

use Database\Factories\PipelineStageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineStage extends Model
{
    /** @use HasFactory<PipelineStageFactory> */
    use HasFactory;

    protected $fillable = [
        'pipeline_id', 'name', 'position', 'probability', 'status', 'is_won', 'is_lost',
    ];

    protected function casts(): array
    {
        return ['is_won' => 'boolean', 'is_lost' => 'boolean'];
    }

    /** @return BelongsTo<Pipeline, $this> */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /** @return HasMany<Opportunity, $this> */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'pipeline_stage_id');
    }
}
