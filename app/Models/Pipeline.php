<?php

namespace App\Models;

use Database\Factories\PipelineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pipeline extends Model
{
    /** @use HasFactory<PipelineFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'description', 'is_default', 'status'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /** @return HasMany<PipelineStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class)->orderBy('position');
    }

    /** @return HasMany<Opportunity, $this> */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }
}
