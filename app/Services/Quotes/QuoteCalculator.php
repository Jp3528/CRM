<?php

namespace App\Services\Quotes;

use Illuminate\Validation\ValidationException;

/**
 * Fuente única de cálculo comercial. Todo dinero como strings decimales
 * (BCMath, 2 decimales); nunca float como fuente de verdad.
 *
 * Fórmulas por línea:
 *   line_base       = quantity × unit_price
 *   discount_amount = line_base × percent/100 | valor fijo (<= line_base)
 *   taxable_base    = line_base - discount_amount
 *   tax_amount      = taxable_base × tax_rate/100
 *   line_total      = taxable_base + tax_amount
 *
 * Totales: subtotal = Σ bases; discount_total = Σ descuentos;
 * tax_total = Σ impuestos; total = subtotal - discount_total + tax_total.
 */
final class QuoteCalculator
{
    /**
     * @param  array<int, array{quantity: mixed, unit_price: mixed, discount_type?: string, discount_value?: mixed, tax_rate?: mixed}>  $lines
     * @return array{lines: array<int, array<string, string>>, subtotal: string, discount_total: string, tax_total: string, total: string}
     *
     * @throws ValidationException
     */
    public function calculate(array $lines): array
    {
        $computed = [];
        $subtotal = '0.00';
        $discountTotal = '0.00';
        $taxTotal = '0.00';

        foreach (array_values($lines) as $i => $line) {
            $computed[] = $this->calculateLine($line, $i);
            $subtotal = bcadd($subtotal, $computed[$i]['subtotal'], 2);
            $discountTotal = bcadd($discountTotal, $computed[$i]['discount_amount'], 2);
            $taxTotal = bcadd($taxTotal, $computed[$i]['tax_amount'], 2);
        }

        return [
            'lines' => $computed,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'total' => bcadd(bcsub($subtotal, $discountTotal, 2), $taxTotal, 2),
        ];
    }

    /**
     * @param  array{quantity: mixed, unit_price: mixed, discount_type?: string, discount_value?: mixed, tax_rate?: mixed}  $line
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    public function calculateLine(array $line, int $index = 0): array
    {
        $quantity = $this->decimal($line['quantity'] ?? 0, 3);
        $unitPrice = $this->decimal($line['unit_price'] ?? 0, 2);
        $discountType = $line['discount_type'] ?? 'none';
        $discountValue = $this->decimal($line['discount_value'] ?? 0, 2);
        $taxRate = $this->decimal($line['tax_rate'] ?? 0, 2);

        if (bccomp($quantity, '0', 3) <= 0) {
            throw ValidationException::withMessages([
                "items.{$index}.quantity" => 'La cantidad debe ser mayor a 0.',
            ]);
        }

        if (bccomp($taxRate, '0', 2) < 0 || bccomp($taxRate, '100', 2) > 0) {
            throw ValidationException::withMessages([
                "items.{$index}.tax_rate" => 'El impuesto debe estar entre 0 y 100.',
            ]);
        }

        $base = bcmul($quantity, $unitPrice, 2);

        $discount = match ($discountType) {
            'percentage' => $this->percentageDiscount($base, $discountValue, $index),
            'fixed' => $this->fixedDiscount($base, $discountValue, $index),
            default => '0.00',
        };

        $taxable = bcsub($base, $discount, 2);
        $tax = bcmul($taxable, bcdiv($taxRate, '100', 6), 2);

        return [
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_type' => $discountType === 'fixed' || $discountType === 'percentage' ? $discountType : 'none',
            'discount_value' => $discountType === 'none' ? '0.00' : $discountValue,
            'tax_rate' => $taxRate,
            'subtotal' => $base,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total' => bcadd($taxable, $tax, 2),
        ];
    }

    /**
     * @throws ValidationException
     */
    private function percentageDiscount(string $base, string $percent, int $index): string
    {
        if (bccomp($percent, '0', 2) < 0 || bccomp($percent, '100', 2) > 0) {
            throw ValidationException::withMessages([
                "items.{$index}.discount_value" => 'El descuento porcentual debe estar entre 0 y 100.',
            ]);
        }

        return bcmul($base, bcdiv($percent, '100', 6), 2);
    }

    /**
     * @throws ValidationException
     */
    private function fixedDiscount(string $base, string $value, int $index): string
    {
        if (bccomp($value, '0', 2) < 0) {
            throw ValidationException::withMessages([
                "items.{$index}.discount_value" => 'El descuento fijo no puede ser negativo.',
            ]);
        }

        if (bccomp($value, $base, 2) > 0) {
            throw ValidationException::withMessages([
                "items.{$index}.discount_value" => 'El descuento fijo no puede superar el base de la línea.',
            ]);
        }

        return $value;
    }

    private function decimal(mixed $value, int $scale): string
    {
        if (! is_numeric($value)) {
            return number_format(0, $scale, '.', '');
        }

        return bcadd((string) $value, '0', $scale);
    }
}
