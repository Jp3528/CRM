<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sku' => strtoupper(fake()->unique()->bothify('PRD-####-??')),
            'name' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'category_id' => ProductCategory::factory(),
            'unit' => fake()->randomElement(['unit', 'hour', 'day', 'service', 'license', 'package']),
            'price' => number_format(fake()->randomFloat(2, 10000, 5000000), 2, '.', ''),
            'cost' => number_format(fake()->randomFloat(2, 5000, 2000000), 2, '.', ''),
            'tax_rate' => number_format(fake()->randomElement([0, 5, 18, 19]), 2, '.', ''),
            'status' => 'active',
            'created_by' => User::factory(),
        ];
    }
}
