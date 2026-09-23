<?php

namespace App\Services\Reports;

use App\Models\Opportunity;
use App\Models\PipelineStage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Forecast operacional ponderado (NO IA, NO predicción estadística).
 *
 * weighted = amount * probability / 100 (numeric/decimal, por moneda).
 * Solo status=open con expected_close_date en rango; sin fecha se reporta
 * aparte como dato de calidad. Won actual va separado (actual_close_date).
 * Sin snapshots históricos: forecast live del estado actual.
 */
final class ForecastService
{
    public function __construct(private User $user, private ReportFilters $filters) {}

    /** @return array<string, mixed> */
    public function build(): array
    {
        $inRange = $this->openScoped()
            ->whereNotNull('opportunities.expected_close_date')
            ->whereDate('opportunities.expected_close_date', '>=', $this->filters->from)
            ->whereDate('opportunities.expected_close_date', '<=', $this->filters->to);

        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', opportunities.expected_close_date)"
            : "TO_CHAR(opportunities.expected_close_date, 'YYYY-MM')";

        $buckets = (clone $inRange)
            ->select(DB::raw("{$monthExpr} as month"), 'opportunities.currency', DB::raw('COUNT(*) as deals'), DB::raw('SUM(opportunities.amount) as amount'), DB::raw('SUM(opportunities.amount * opportunities.probability / 100) as weighted'))
            ->groupBy('month', 'opportunities.currency')
            ->orderBy('month')
            ->get();

        $byMonth = [];
        foreach ($buckets as $row) {
            $byMonth[$row->month] ??= ['label' => $row->month, 'deals' => 0, 'amounts' => [], 'weighted' => []];
            $byMonth[$row->month]['deals'] += (int) $row->deals;
            if ($row->amount !== null) {
                $byMonth[$row->month]['amounts'][$row->currency] = (string) $row->amount;
            }
            if ($row->weighted !== null) {
                $byMonth[$row->month]['weighted'][$row->currency] = (string) $row->weighted;
            }
        }

        $totals = (clone $inRange)
            ->select('opportunities.currency', DB::raw('COUNT(*) as deals'), DB::raw('SUM(opportunities.amount) as amount'), DB::raw('SUM(opportunities.amount * opportunities.probability / 100) as weighted'), DB::raw('AVG(opportunities.probability) as avg_probability'))
            ->groupBy('opportunities.currency')
            ->orderBy('opportunities.currency')
            ->get();

        $missing = $this->openScoped()->whereNull('opportunities.expected_close_date');
        $missingCount = (clone $missing)->count();
        $missingAmounts = (clone $missing)
            ->select('opportunities.currency', DB::raw('SUM(opportunities.amount) as amount'))
            ->groupBy('opportunities.currency')->pluck('amount', 'currency')
            ->map(fn ($v) => $v !== null ? (string) $v : null)->all();

        // Won actual en el rango (serie separada, sin mezclar con ponderado).
        $won = Opportunity::visibleTo($this->user)->where('opportunities.status', 'won')
            ->whereNotNull('opportunities.actual_close_date')
            ->whereDate('opportunities.actual_close_date', '>=', $this->filters->from)
            ->whereDate('opportunities.actual_close_date', '<=', $this->filters->to);
        if ($this->filters->ownerId !== null) {
            $won->where('opportunities.owner_id', $this->filters->ownerId);
        }
        $wonRows = (clone $won)
            ->select('opportunities.currency', DB::raw('COUNT(*) as deals'), DB::raw('SUM(opportunities.amount) as amount'))
            ->groupBy('opportunities.currency')->orderBy('opportunities.currency')->get();

        return [
            'months' => array_values($byMonth),
            'totals' => $totals->map(fn ($row) => [
                'currency' => $row->currency,
                'deals' => (int) $row->deals,
                'amount' => $row->amount !== null ? (string) $row->amount : null,
                'weighted' => $row->weighted !== null ? (string) $row->weighted : null,
                'avg_probability' => $row->avg_probability !== null ? round((float) $row->avg_probability, 1) : null,
            ])->all(),
            'missing_close_date' => ['deals' => $missingCount, 'amounts' => $missingAmounts],
            'by_stage' => $this->byStage($inRange),
            'by_owner' => $this->byOwner($inRange),
            'won_actual' => $wonRows->map(fn ($row) => [
                'currency' => $row->currency,
                'deals' => (int) $row->deals,
                'amount' => $row->amount !== null ? (string) $row->amount : null,
            ])->all(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function byStage($inRange): array
    {
        $rows = (clone $inRange)
            ->select('opportunities.pipeline_stage_id', 'opportunities.currency', DB::raw('COUNT(*) as deals'), DB::raw('SUM(opportunities.amount) as amount'), DB::raw('SUM(opportunities.amount * opportunities.probability / 100) as weighted'))
            ->groupBy('opportunities.pipeline_stage_id', 'opportunities.currency')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $stages = PipelineStage::whereIn('id', $rows->pluck('pipeline_stage_id')->unique()->all())
            ->orderBy('position')->get()->keyBy('id');

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->pipeline_stage_id] ??= [
                'stage' => $stages[$row->pipeline_stage_id]?->name ?? '—',
                'deals' => 0, 'amounts' => [], 'weighted' => [],
            ];
            $grouped[$row->pipeline_stage_id]['deals'] += (int) $row->deals;
            if ($row->amount !== null) {
                $grouped[$row->pipeline_stage_id]['amounts'][$row->currency] = (string) $row->amount;
            }
            if ($row->weighted !== null) {
                $grouped[$row->pipeline_stage_id]['weighted'][$row->currency] = (string) $row->weighted;
            }
        }

        return array_values($grouped);
    }

    /** @return array<int, array<string, mixed>> Top 10 responsables en alcance. */
    private function byOwner($inRange): array
    {
        $rows = (clone $inRange)->whereNotNull('opportunities.owner_id')
            ->select('opportunities.owner_id', 'opportunities.currency', DB::raw('COUNT(*) as deals'), DB::raw('SUM(opportunities.amount) as amount'), DB::raw('SUM(opportunities.amount * opportunities.probability / 100) as weighted'))
            ->groupBy('opportunities.owner_id', 'opportunities.currency')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $names = User::whereIn('id', $rows->pluck('owner_id')->unique()->all())->pluck('name', 'id');

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->owner_id] ??= [
                'owner_id' => $row->owner_id,
                'name' => $names[$row->owner_id] ?? 'Sin responsable',
                'deals' => 0, 'amounts' => [], 'weighted' => [],
            ];
            $grouped[$row->owner_id]['deals'] += (int) $row->deals;
            if ($row->amount !== null) {
                $grouped[$row->owner_id]['amounts'][$row->currency] = (string) $row->amount;
            }
            if ($row->weighted !== null) {
                $grouped[$row->owner_id]['weighted'][$row->currency] = (string) $row->weighted;
            }
        }

        usort($grouped, fn ($a, $b) => $b['deals'] <=> $a['deals']);

        return array_slice(array_values($grouped), 0, 10);
    }

    private function openScoped()
    {
        $query = Opportunity::visibleTo($this->user)->where('opportunities.status', 'open');

        if ($this->filters->ownerId !== null) {
            $query->where('opportunities.owner_id', $this->filters->ownerId);
        }

        return $query;
    }

    /**
     * Rango default del forecast: mes actual + próximos 2 meses.
     *
     * @param  array<string, mixed>  $input
     */
    public static function defaultFilters(array $input, User $user): ReportFilters
    {
        if (! empty($input['preset']) || ! empty($input['date_from']) || ! empty($input['date_to'])) {
            return ReportFilters::resolve($input, $user);
        }

        $now = CarbonImmutable::now(config('app.timezone'));

        return ReportFilters::resolve([
            'date_from' => $now->startOfMonth()->toDateString(),
            'date_to' => $now->addMonthsNoOverflow(2)->endOfMonth()->toDateString(),
            'owner_id' => $input['owner_id'] ?? null,
        ], $user);
    }
}
