<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /** Datos demo mínimos e idempotentes (solo desarrollo). */
    public const CATEGORIES = ['Servicios', 'Licencias', 'Hardware'];

    public const PRODUCTS = [
        ['CONSULT-01', 'Consultoría por hora', 'Servicios', 'hour', '180000.00', '19.00'],
        ['LIC-PRO-01', 'Licencia Pro anual', 'Licencias', 'license', '1200000.00', '19.00'],
        ['SRV-SUP-01', 'Soporte mensual', 'Servicios', 'service', '450000.00', '19.00'],
        ['HW-KIT-01', 'Kit de implementación', 'Hardware', 'package', '2500000.00', '19.00'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name) {
            ProductCategory::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'status' => 'active']
            );
        }

        foreach (self::PRODUCTS as [$sku, $name, $category, $unit, $price, $tax]) {
            Product::firstOrCreate(
                ['sku' => $sku],
                [
                    'name' => $name,
                    'description' => "{$name} (demo).",
                    'category_id' => ProductCategory::where('slug', Str::slug($category))->first()?->id,
                    'unit' => $unit,
                    'price' => $price,
                    'cost' => $price,
                    'tax_rate' => $tax,
                    'status' => 'active',
                ]
            );
        }
    }
}
