<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityStageHistory extends Model
{
    protected $table = 'opportunity_stage_history';

    protected $fillable = [
        'opportunity_id', 'from_stage_id', 'to_stage_id',
        'changed_by', 'changed_at', 'notes', 'metadata',
    ];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime', 'metadata' => 'array'];
    }

    /** @return BelongsTo<Opportunity, $this> */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /** @return BelongsTo<PipelineStage, $this> */
    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'from_stage_id');
    }

    /** @return BelongsTo<PipelineStage, $this> */
    public function toStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'to_stage_id');
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
