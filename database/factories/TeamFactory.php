<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' Team';

        return [
            'name' => $name,
            'slug' => strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)),
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }
}
