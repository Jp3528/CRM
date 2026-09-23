<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trade_name' => fake()->company(),
            'legal_name' => fake()->company().' S.A.S.',
            'tax_id' => fake()->unique()->numerify('900###-#'),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'website' => 'https://'.fake()->domainName(),
            'industry' => fake()->randomElement(['Tecnología', 'Manufactura', 'Servicios', 'Comercio', 'Salud', 'Educación']),
            'company_size' => fake()->randomElement(['1-10', '11-50', '51-200', '201-500', '500+']),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'region' => fake()->state(),
            'country' => 'Colombia',
            'postal_code' => fake()->postcode(),
            'status' => 'active',
            'owner_id' => User::factory(),
            'notes' => fake()->sentence(),
        ];
    }
}
