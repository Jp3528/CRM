<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'company_name' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'source' => fake()->randomElement(['web', 'referral', 'event', 'cold_call', 'social']),
            'status' => 'new',
            'score' => fake()->numberBetween(0, 100),
            'owner_id' => User::factory(),
            'estimated_value' => fake()->randomFloat(2, 100000, 50000000),
            'notes' => fake()->sentence(),
        ];
    }
}
