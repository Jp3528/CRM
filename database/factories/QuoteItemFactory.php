<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteItem>
 */
class QuoteItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),
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
