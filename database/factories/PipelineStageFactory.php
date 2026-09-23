<?php

namespace Database\Factories;

use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PipelineStage>
 */
class PipelineStageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pipeline_id' => Pipeline::factory(),
            'name' => fake()->unique()->words(2, true),
            'position' => fake()->numberBetween(1, 10),
            'probability' => fake()->numberBetween(0, 100),
            'status' => 'active',
            'is_won' => false,
            'is_lost' => false,
        ];
    }
}
