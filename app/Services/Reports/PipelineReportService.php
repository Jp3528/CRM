<?php

namespace App\Services\Reports;

use App\Models\Opportunity;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pipeline abierto (status=open) + distribución por etapa + buckets de cierre.
 * Montos ponderados con SQL numeric (amount * probability / 100), por moneda.
 */
final class PipelineReportService
{
    public function __construct(private User $user, private ReportFilters $filters) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $rows = $this->openScoped()
            ->select('opportunities.currency', DB::raw('COUNT(*) as deals'), DB::raw('SUM(opportunities.amount) as amount'), DB::raw('SUM(opportunities.amount * opportunities.probability / 100) as weighted'))
            ->groupBy('opportunities.currency')
            ->orderBy('opportunities.currency')
            ->get();

        $byCurrency = [];
        $deals = 0;

        foreach ($rows as $row) {
            $deals += (int) $row->deals;
            $byCurrency[$row->currency] = [
                'deals' => (int) $row->deals,
                'amount' => $row->amount !== null ? (string) $row->amount : null,
                'weighted' => $row->weighted !== null ? (string) $row->weighted : null,
                'average' => $row->deals > 0 && $row->amount !== null
                    ? bcdiv((string) $row->amount, (string) $row->deals, 2)
                    : null,
            ];
        }

        return ['deals' => $deals, 'by_currency' => $byCurrency];
    }

    /** @return array<int, array<string, mixed>> */
    public function byStage(): array
    {
        $rows = $this->openScoped()
            ->select(
                'opportunities.pipeline_stage_id', 'opportunities.currency',
                DB::raw('COUNT(*) as deals'),
                DB::raw('SUM(opportunities.amount) as amount'),
                DB::raw('SUM(opportunities.amount * opportunities.probability / 100) as weighted')
            )
            ->groupBy('opportunities.pipeline_stage_id', 'opportunities.currency')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $stages = PipelineStage::whereIn('id', $rows->pluck('pipeline_stage_id')->unique()->all())
            ->with('pipeline:id,name')
            ->orderBy('position')
            ->get()
            ->keyBy('id');

        $grouped = [];
        foreach ($rows as $row) {
            $stage = $stages[$row->pipeline_stage_id] ?? null;
            $grouped[$row->pipeline_stage_id] ??= [
                'stage_id' => $row->pipeline_stage_id,
                'stage' => $stage?->name ?? '—',
                'pipeline' => $stage?->pipeline?->name ?? '—',
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

    /** @return array<string, mixed> */
    public function closeBuckets(): array
    {
        $today = today()->toDateString();
        $monthEnd = today()->endOfMonth()->toDateString();
        $nextMonthEnd = today()->addMonthNoOverflow()->endOfMonth()->toDateString();

        $bucket = function (?string $from, ?string $to, bool $isNull = false) {
            $q = $this->openScoped();
            if ($isNull) {
                $q->whereNull('opportunities.expected_close_date');
            } else {
                $q->whereNotNull('opportunities.expected_close_date');
                if ($from !== null) {
                    $q->whereDate('opportunities.expected_close_date', '>=', $from);
                }
                if ($to !== null) {
                    $q->whereDate('opportunities.expected_close_date', '<=', $to);
                }
            }

            return [
                'deals' => (clone $q)->count(),
                'amounts' => (clone $q)->select('opportunities.currency', DB::raw('SUM(opportunities.amount) as amount'))
                    ->groupBy('opportunities.currency')->pluck('amount', 'currency')
                    ->map(fn ($v) => (string) $v)->all(),
            ];
        };

        return [
            'overdue' => $bucket(null, today()->subDay()->toDateString()),
            'this_month' => $bucket($today, $monthEnd),
            'next_month' => $bucket(today()->addMonthNoOverflow()->startOfMonth()->toDateString(), $nextMonthEnd),
            'later' => $bucket(today()->addMonthsNoOverflow(2)->startOfMonth()->toDateString(), null),
            'no_date' => $bucket(null, null, true),
        ];
    }

    /** @return array<string, mixed> */
    public function stale(int $days = 30): array
    {
        $query = $this->openScoped()
            ->where('opportunities.updated_at', '<', now()->subDays($days))
            ->with(['owner:id,name', 'company' => fn ($q) => $q->visibleTo($this->user)->select('id', 'trade_name', 'owner_id')])
            ->orderBy('opportunities.updated_at')
            ->limit(50);

        $items = $query->get();

        return [
            'days' => $days,
            'total' => $this->openScoped()->where('opportunities.updated_at', '<', now()->subDays($days))->count(),
            'items' => $items,
        ];
    }

    private function openScoped()
    {
        $query = Opportunity::visibleTo($this->user)->where('opportunities.status', 'open');

        if ($this->filters->ownerId !== null) {
            $query->where('opportunities.owner_id', $this->filters->ownerId);
        }

        return $query;
    }
}
