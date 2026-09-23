<?php

namespace App\Support;

/**
 * Formato consistente de números en reportes (sin duplicar por vista).
 */
final class ReportFormat
{
    public static function count(int|float|null $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format($value, 0, '.', ',');
    }

    public static function money(string|float|int|null $value, ?string $currency = null): string
    {
        if ($value === null) {
            return '—';
        }

        $formatted = number_format((float) $value, 2, '.', ',');

        return $currency ? "{$formatted} {$currency}" : $formatted;
    }

    /** Tasa 0–100 o null cuando no hay base. */
    public static function percent(?float $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        return number_format($value, 1).'%';
    }

    /** Variación vs período anterior; null si no calculable. */
    public static function trend(?float $current, ?float $previous): ?string
    {
        if ($current === null || $previous === null || $previous == 0) {
            return null;
        }

        $pct = ($current - $previous) / abs($previous) * 100;
        $sign = $pct > 0 ? '+' : '';

        return $sign.number_format($pct, 1).'%';
    }

    public static function isPositive(?string $trend): ?bool
    {
        if ($trend === null) {
            return null;
        }

        return str_starts_with($trend, '+');
    }

    /** Segundos internos → 45s / 12m / 2h 15m / 1d 3h. */
    public static function duration(int|float|null $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        $seconds = (int) round($seconds);

        if ($seconds < 60) {
            return "{$seconds}s";
        }

        if ($seconds < 3600) {
            return ((int) floor($seconds / 60)).'m';
        }

        if ($seconds < 86400) {
            $h = (int) floor($seconds / 3600);
            $m = (int) floor(($seconds % 3600) / 60);

            return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
        }

        $d = (int) floor($seconds / 86400);
        $h = (int) floor(($seconds % 86400) / 3600);

        return $h > 0 ? "{$d}d {$h}h" : "{$d}d";
    }
}
