<?php

namespace Database\Factories;

use App\Models\Pipeline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pipeline>
 */
class PipelineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Pipeline',
            'description' => fake()->sentence(),
            'is_default' => false,
            'status' => 'active',
        ];
    }
}
