<?php

namespace Database\Factories;

use App\Models\Communication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Communication>
 */
class CommunicationFactory extends Factory
{
    protected $model = Communication::class;

    public function definition(): array
    {
        return [
            'channel' => fake()->randomElement(Communication::CHANNELS),
            'direction' => 'outbound',
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'status' => 'draft',
            'owner_id' => User::factory(),
            'created_by' => User::factory(),
            'metadata' => ['simulated' => false, 'provider' => null],
        ];
    }
}
