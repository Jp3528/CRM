<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Oportunidad '.fake()->words(3, true),
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 500000, 200000000),
            'currency' => 'USD',
            'probability' => fake()->numberBetween(0, 100),
            'expected_close_date' => fake()->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'status' => 'open',
            'owner_id' => User::factory(),
            'pipeline_id' => Pipeline::factory(),
            'pipeline_stage_id' => PipelineStage::factory(),
            'company_id' => Company::factory(),
            'contact_id' => Contact::factory(),
        ];
    }
}
