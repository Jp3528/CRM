<?php

namespace App\Support;

/**
 * Evaluador de condiciones con tipos seguros.
 *
 * Todas las condiciones de una automatización se combinan con AND.
 * Dinero y decimales se comparan con bccomp (nunca float).
 */
final class ConditionEvaluator
{
    /**
     * @param  array<int, array{field: string, operator: string, value: mixed}>  $conditions
     * @param  array<string, string>  $fieldTypes  field => type
     * @param  array<string, mixed>  $context
     */
    public static function passes(array $conditions, array $fieldTypes, array $context): bool
    {
        foreach ($conditions as $condition) {
            $field = $condition['field'];
            $operator = $condition['operator'];
            $expected = $condition['value'] ?? null;
            $actual = $context[$field] ?? null;

            if (! self::evaluate($fieldTypes[$field] ?? 'string', $operator, $actual, $expected)) {
                return false;
            }
        }

        return true;
    }

    public static function evaluate(string $type, string $operator, mixed $actual, mixed $expected): bool
    {
        return match ($operator) {
            'is_null' => $actual === null || $actual === '',
            'not_null' => $actual !== null && $actual !== '',
            'equals' => self::equals($type, $actual, $expected),
            'not_equals' => ! self::equals($type, $actual, $expected),
            'greater_than' => self::compareWith($type, $actual, $expected, fn (int $comparison) => $comparison > 0),
            'greater_or_equal' => self::compareWith($type, $actual, $expected, fn (int $comparison) => $comparison >= 0),
            'less_than' => self::compareWith($type, $actual, $expected, fn (int $comparison) => $comparison < 0),
            'less_or_equal' => self::compareWith($type, $actual, $expected, fn (int $comparison) => $comparison <= 0),
            'in' => self::inList($type, $actual, (array) $expected),
            'not_in' => ! self::inList($type, $actual, (array) $expected),
            'contains' => is_string($actual) && is_string($expected)
                && mb_stripos($actual, $expected) !== false,
            default => false,
        };
    }

    private static function equals(string $type, mixed $actual, mixed $expected): bool
    {
        if ($actual === null || $expected === null) {
            return $actual === $expected;
        }

        if (in_array($type, ['integer', 'decimal', 'user_id'], true)) {
            if (! is_numeric($actual) || ! is_numeric($expected)) {
                return false;
            }

            return bccomp(self::decimalString($actual), self::decimalString($expected), 6) === 0;
        }

        return (string) $actual === (string) $expected;
    }

    private static function compareWith(string $type, mixed $actual, mixed $expected, callable $predicate): bool
    {
        $comparison = self::compare($type, $actual, $expected);

        return $comparison !== null && $predicate($comparison);
    }

    /**
     * @return int|null -1|0|1, null cuando no es comparable de forma segura.
     */
    private static function compare(string $type, mixed $actual, mixed $expected): ?int
    {
        if ($actual === null || $actual === '' || $expected === null || $expected === '') {
            return null;
        }

        if (in_array($type, ['integer', 'decimal', 'user_id'], true)) {
            if (! is_numeric($actual) || ! is_numeric($expected)) {
                return null;
            }

            return bccomp(self::decimalString($actual), self::decimalString($expected), 6);
        }

        return (string) $actual <=> (string) $expected;
    }

    private static function inList(string $type, mixed $actual, array $expected): bool
    {
        foreach ($expected as $candidate) {
            if (self::equals($type, $actual, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private static function decimalString(mixed $value): string
    {
        if (is_numeric($value)) {
            return (string) $value;
        }

        return '0';
    }
}
