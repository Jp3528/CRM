<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'mobile' => fake()->phoneNumber(),
            'job_title' => fake()->jobTitle(),
            'department' => fake()->randomElement(['Ventas', 'Marketing', 'Operaciones', 'Finanzas', 'TI']),
            'company_id' => Company::factory(),
            'owner_id' => User::factory(),
            'status' => 'active',
            'notes' => fake()->sentence(),
        ];
    }
}
