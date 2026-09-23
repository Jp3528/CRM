<?php

namespace Database\Factories;

use App\Models\Automation;
use App\Models\AutomationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationRun>
 */
class AutomationRunFactory extends Factory
{
    protected $model = AutomationRun::class;

    public function definition(): array
    {
        return [
            'automation_id' => Automation::factory(),
            'event_uuid' => fake()->uuid(),
            'trigger_type' => 'lead.created',
            'subject_type' => 'lead',
            'subject_id' => 1,
            'status' => 'success',
            'context' => ['entity_type' => 'lead', 'entity_id' => 1],
            'result' => ['actions' => []],
            'started_at' => now(),
            'finished_at' => now(),
        ];
    }
}
