<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'INV-'.date('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'company_id' => Company::factory(),
            'owner_id' => User::factory(),
            'status' => 'draft',
            'currency' => 'USD',
            'issue_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'subtotal' => '0.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'total' => '0.00',
        ];
    }
}
