<?php

namespace App\Services\Sales;

use App\Services\Quotes\QuoteCalculator;

/**
 * Cálculo de ventas manuales. Delega en QuoteCalculator: la matemática
 * (BCMath, 2 decimales) es idéntica y la fuente de verdad sigue siendo una sola.
 * Los mensajes de error usan claves items.* (mismo formato de formulario).
 */
final class SaleCalculator
{
    public function __construct(private QuoteCalculator $quotes) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{lines: array<int, array<string, string>>, subtotal: string, discount_total: string, tax_total: string, total: string}
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function calculate(array $lines): array
    {
        return $this->quotes->calculate($lines);
    }
}
