<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Support\DataScope;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Filtros globales de reportes (rango + responsable).
 *
 * El frontend envía preset o fechas explícitas; el backend resuelve fechas
 * reales. El owner se valida contra filterableUsers (fuera de alcance → 422).
 * Rango máximo: 5 años por consulta interactiva.
 */
final class ReportFilters
{
    public const MAX_DAYS = 1826;

    public const PRESETS = ['today', '7d', '30d', 'month', 'prev_month', 'quarter', 'year'];

    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly ?int $ownerId,
        public readonly string $preset = 'month',
    ) {}

    /**
     * @param  array<string, mixed>  $input  Datos ya validados del request.
     *
     * @throws ValidationException
     */
    public static function resolve(array $input, User $user, string $defaultPreset = 'month'): self
    {
        [$from, $to, $preset] = self::resolveDates($input, $defaultPreset);

        $days = CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1;

        if ($days > self::MAX_DAYS) {
            throw ValidationException::withMessages([
                'date_to' => 'Rango máximo: 5 años por consulta.',
            ]);
        }

        $ownerId = isset($input['owner_id']) && $input['owner_id'] !== null && $input['owner_id'] !== ''
            ? (int) $input['owner_id']
            : null;

        if ($ownerId !== null && ! in_array($ownerId, DataScope::filterableUserIds($user), true)) {
            throw ValidationException::withMessages([
                'owner_id' => 'Responsable fuera de tu alcance.',
            ]);
        }

        return new self($from, $to, $ownerId, $preset);
    }

    /** @return array{0: string, 1: string, 2: string} */
    private static function resolveDates(array $input, string $default): array
    {
        $explicitFrom = $input['date_from'] ?? null;
        $explicitTo = $input['date_to'] ?? null;

        if ($explicitFrom || $explicitTo) {
            $tz = config('app.timezone');
            $from = $explicitFrom ? CarbonImmutable::parse($explicitFrom, $tz)->toDateString() : CarbonImmutable::now($tz)->startOfMonth()->toDateString();
            $to = $explicitTo ? CarbonImmutable::parse($explicitTo, $tz)->toDateString() : CarbonImmutable::now($tz)->toDateString();

            if ($from > $to) {
                throw ValidationException::withMessages([
                    'date_from' => 'La fecha inicial debe ser anterior o igual a la final.',
                ]);
            }

            return [$from, $to, 'custom'];
        }

        $preset = in_array($input['preset'] ?? null, self::PRESETS, true)
            ? $input['preset']
            : $default;

        $now = CarbonImmutable::now(config('app.timezone'));

        [$from, $to] = match ($preset) {
            'today' => [$now->toDateString(), $now->toDateString()],
            '7d' => [$now->subDays(6)->toDateString(), $now->toDateString()],
            '30d' => [$now->subDays(29)->toDateString(), $now->toDateString()],
            'prev_month' => [
                $now->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $now->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'quarter' => [$now->startOfQuarter()->toDateString(), $now->endOfQuarter()->toDateString()],
            'year' => [$now->startOfYear()->toDateString(), $now->endOfYear()->toDateString()],
            default => [$now->startOfMonth()->toDateString(), $now->endOfMonth()->toDateString()],
        };

        return [$from, $to, $preset];
    }

    /** Período anterior de igual duración (para comparativas). @return array{0: string, 1: string} */
    public function previousPeriod(): array
    {
        $from = CarbonImmutable::parse($this->from);
        $to = CarbonImmutable::parse($this->to);
        $days = $from->diffInDays($to) + 1;

        return [
            $from->subDays($days)->toDateString(),
            $from->subDay()->toDateString(),
        ];
    }

    public function days(): int
    {
        return CarbonImmutable::parse($this->from)->diffInDays(CarbonImmutable::parse($this->to)) + 1;
    }

    /** Granularidad de tendencia: día (<=31), mes en adelante. */
    public function trendBucket(): string
    {
        return $this->days() <= 31 ? 'day' : 'month';
    }

    /** @return array<string, mixed> */
    public function forView(): array
    {
        return [
            'preset' => $this->preset,
            'date_from' => $this->from,
            'date_to' => $this->to,
            'owner_id' => $this->ownerId,
        ];
    }
}
