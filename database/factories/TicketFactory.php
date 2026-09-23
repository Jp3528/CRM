<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'TKT-'.date('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'category_id' => TicketCategory::factory(),
            'subject' => ucfirst(fake()->words(4, true)),
            'description' => fake()->paragraph(),
            'status' => 'new',
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'channel' => fake()->randomElement(['web', 'email', 'phone', 'manual']),
            'requester_name' => fake()->name(),
            'requester_email' => fake()->safeEmail(),
            'assigned_to' => User::factory(),
            'created_by' => User::factory(),
        ];
    }
}
