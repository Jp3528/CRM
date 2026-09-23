<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'position' => 0,
            'description' => fake()->sentence(4),
            'unit' => 'unit',
            'quantity' => number_format(fake()->randomFloat(3, 1, 10), 3, '.', ''),
            'unit_price' => number_format(fake()->randomFloat(2, 10000, 500000), 2, '.', ''),
            'discount_type' => 'none',
            'discount_value' => '0.00',
            'tax_rate' => number_format(18, 2, '.', ''),
            'subtotal' => '0.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'total' => '0.00',
        ];
    }
}
