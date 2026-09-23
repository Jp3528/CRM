<?php

namespace Database\Factories;

use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageTemplate>
 */
class MessageTemplateFactory extends Factory
{
    protected $model = MessageTemplate::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->words(3, true)),
            'channel' => fake()->randomElement(MessageTemplate::CHANNELS),
            'subject' => fake()->sentence(4),
            'body' => 'Hola {{full_name}}, te contactamos de {{company_name}}.',
            'status' => 'active',
            'owner_id' => User::factory(),
            'created_by' => User::factory(),
        ];
    }
}
