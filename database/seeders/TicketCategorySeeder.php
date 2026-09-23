<?php

namespace Database\Seeders;

use App\Models\TicketCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TicketCategorySeeder extends Seeder
{
    /** Categorías mínimas de soporte (idempotentes). */
    public const CATEGORIES = ['General', 'Facturación', 'Comercial', 'Soporte técnico'];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name) {
            TicketCategory::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'status' => 'active']
            );
        }
    }
}
