<?php

namespace App\Services\Reports;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de ventas (confirmed + completed; cancelled por separado).
 * Totales siempre agrupados POR moneda (sin FX, sin sumas cruzadas).
 */
final class SalesReportService
{
    public function __construct(private User $user, private ReportFilters $filters) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $base = $this->scoped()->whereIn('sales.status', ['confirmed', 'completed'])
            ->whereBetween('sales.sale_date', [$this->filters->from, $this->filters->to]);

        $rows = (clone $base)
            ->select('sales.currency', DB::raw('COUNT(*) as deals'), DB::raw('SUM(sales.total) as total'))
            ->groupBy('sales.currency')
            ->orderBy('sales.currency')
            ->get();

        [$prevFrom, $prevTo] = $this->filters->previousPeriod();
        $prev = $this->scoped()->whereIn('sales.status', ['confirmed', 'completed'])
            ->whereBetween('sales.sale_date', [$prevFrom, $prevTo])
            ->select('sales.currency', DB::raw('SUM(sales.total) as total'))
            ->groupBy('sales.currency')
            ->pluck('total', 'currency');

        $byCurrency = [];
        $dealCount = 0;

        foreach ($rows as $row) {
            $dealCount += (int) $row->deals;
            $current = $row->total !== null ? (string) $row->total : null;
            $previous = isset($prev[$row->currency]) ? (string) $prev[$row->currency] : null;
            $byCurrency[$row->currency] = [
                'deals' => (int) $row->deals,
                'total' => $current,
                'average' => $row->deals > 0 && $current !== null
                    ? bcdiv($current, (string) $row->deals, 2)
                    : null,
                'previous_total' => $previous,
                'trend' => \App\Support\ReportFormat::trend(
                    $current !== null ? (float) $current : null,
                    $previous !== null ? (float) $previous : null
                ),
            ];
        }

        $cancelled = $this->scoped()->where('sales.status', 'cancelled')
            ->whereBetween('sales.sale_date', [$this->filters->from, $this->filters->to])
            ->select('sales.currency', DB::raw('COUNT(*) as deals'), DB::raw('SUM(sales.total) as total'))
            ->groupBy('sales.currency')
            ->orderBy('sales.currency')
            ->get();

        return [
            'deals' => $dealCount,
            'by_currency' => $byCurrency,
            'cancelled' => $cancelled,
        ];
    }

    /** @return array<int, array{label: string, totals: array<string, string>}> */
    public function trend(): array
    {
        $bucket = $this->filters->trendBucket();

        $dateExpr = $bucket === 'day'
            ? "TO_CHAR(sales.sale_date, 'YYYY-MM-DD')"
            : "TO_CHAR(sales.sale_date, 'YYYY-MM')";

        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $dateExpr = $bucket === 'day'
                ? "strftime('%Y-%m-%d', sales.sale_date)"
                : "strftime('%Y-%m', sales.sale_date)";
        }

        $rows = $this->scoped()->whereIn('sales.status', ['confirmed', 'completed'])
            ->whereBetween('sales.sale_date', [$this->filters->from, $this->filters->to])
            ->select(DB::raw("{$dateExpr} as bucket"), 'sales.currency', DB::raw('SUM(sales.total) as total'))
            ->groupBy('bucket', 'sales.currency')
            ->orderBy('bucket')
            ->limit(500)
            ->get();

        $buckets = [];

        foreach ($rows as $row) {
            $buckets[$row->bucket] ??= ['label' => $row->bucket, 'totals' => []];
            $buckets[$row->bucket]['totals'][$row->currency] = (string) $row->total;
        }

        // Rellena buckets vacíos para series continuas (día o mes).
        return array_values($this->fillBuckets($buckets, $bucket));
    }

    /**
     * @param  array<string, array{label: string, totals: array<string, string>}>  $buckets
     * @return array<string, array{label: string, totals: array<string, string>}>
     */
    private function fillBuckets(array $buckets, string $bucket): array
    {
        $start = \Carbon\CarbonImmutable::parse($this->filters->from);
        $end = \Carbon\CarbonImmutable::parse($this->filters->to);
        $out = $buckets;

        if ($bucket === 'day' && $this->filters->days() <= 62) {
            for ($d = $start; $d->lte($end); $d = $d->addDay()) {
                $key = $d->format('Y-m-d');
                $out[$key] ??= ['label' => $key, 'totals' => []];
            }
            ksort($out);
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> Top 10 responsables en alcance. */
    public function byOwner(): array
    {
        $rows = $this->scoped()->whereIn('sales.status', ['confirmed', 'completed'])
            ->whereBetween('sales.sale_date', [$this->filters->from, $this->filters->to])
            ->whereNotNull('sales.owner_id')
            ->select('sales.owner_id', 'sales.currency', DB::raw('COUNT(*) as deals'), DB::raw('SUM(sales.total) as total'))
            ->groupBy('sales.owner_id', 'sales.currency')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $names = User::whereIn('id', $rows->pluck('owner_id')->unique()->all())->pluck('name', 'id');

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->owner_id] ??= ['owner_id' => $row->owner_id, 'name' => $names[$row->owner_id] ?? 'Sin responsable', 'deals' => 0, 'totals' => []];
            $grouped[$row->owner_id]['deals'] += (int) $row->deals;
            $grouped[$row->owner_id]['totals'][$row->currency] = (string) $row->total;
        }

        usort($grouped, fn ($a, $b) => $b['deals'] <=> $a['deals']);

        return array_slice(array_values($grouped), 0, 10);
    }

    /** @return array<int, array<string, mixed>> Top 10 empresas visibles. */
    public function byCompany(): array
    {
        $rows = $this->scoped()->whereIn('sales.status', ['confirmed', 'completed'])
            ->whereBetween('sales.sale_date', [$this->filters->from, $this->filters->to])
            ->whereNotNull('sales.company_id')
            ->select('sales.company_id', 'sales.currency', DB::raw('COUNT(*) as deals'), DB::raw('SUM(sales.total) as total'))
            ->groupBy('sales.company_id', 'sales.currency')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // Solo nombres de empresas visibles (sin fuga por agregados).
        $visible = \App\Models\Company::visibleTo($this->user)
            ->whereIn('id', $rows->pluck('company_id')->unique()->all())
            ->pluck('trade_name', 'id');

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->company_id] ??= [
                'company_id' => $row->company_id,
                'name' => $visible[$row->company_id] ?? null,
                'deals' => 0, 'totals' => [],
            ];
            $grouped[$row->company_id]['deals'] += (int) $row->deals;
            $grouped[$row->company_id]['totals'][$row->currency] = (string) $row->total;
        }

        usort($grouped, fn ($a, $b) => $b['deals'] <=> $a['deals']);

        return array_slice(array_values($grouped), 0, 10);
    }

    private function scoped()
    {
        $query = Sale::visibleTo($this->user);

        if ($this->filters->ownerId !== null) {
            $query->where('sales.owner_id', $this->filters->ownerId);
        }

        return $query;
    }
}
