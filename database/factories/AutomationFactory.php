<?php

namespace Database\Factories;

use App\Models\Automation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Automation>
 */
class AutomationFactory extends Factory
{
    protected $model = Automation::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'status' => 'draft',
            'trigger_type' => 'lead.created',
            'conditions' => [],
            'actions' => [
                [
                    'type' => 'create_task',
                    'title' => 'Seguimiento {{subject_name}}',
                    'description' => null,
                    'priority' => 'medium',
                    'due_in_days' => 3,
                    'assigned_to_mode' => 'subject_owner',
                    'fixed_user_id' => null,
                ],
            ],
            'owner_id' => User::factory(),
            'created_by' => User::factory(),
        ];
    }
}
