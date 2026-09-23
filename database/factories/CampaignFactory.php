<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(Campaign::TYPES),
            'status' => 'draft',
            'owner_id' => User::factory(),
            'start_at' => now()->toDateString(),
            'end_at' => now()->addDays(30)->toDateString(),
            'budget' => fake()->randomFloat(2, 0, 10000),
            'expected_revenue' => fake()->randomFloat(2, 0, 50000),
            'actual_cost' => null,
            'created_by' => User::factory(),
        ];
    }
}
