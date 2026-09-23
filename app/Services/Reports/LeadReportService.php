<?php

namespace App\Services\Reports;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Leads creados en el rango + funnel por estado actual + fuentes + scores.
 *
 * conversion_rate = converted (converted_at en rango) / created (created_at en
 * rango). Definición única usada en Dashboard y reporte.
 */
final class LeadReportService
{
    public function __construct(private User $user, private ReportFilters $filters) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $created = $this->scoped()
            ->whereBetween('leads.created_at', [$this->filters->from.' 00:00:00', $this->filters->to.' 23:59:59'])
            ->count();

        $converted = $this->scoped()
            ->whereNotNull('leads.converted_at')
            ->whereBetween('leads.converted_at', [$this->filters->from.' 00:00:00', $this->filters->to.' 23:59:59'])
            ->count();

        $byStatus = $this->scoped()
            ->whereBetween('leads.created_at', [$this->filters->from.' 00:00:00', $this->filters->to.' 23:59:59'])
            ->select('leads.status', DB::raw('COUNT(*) as total'))
            ->groupBy('leads.status')
            ->pluck('total', 'status')
            ->all();

        return [
            'created' => $created,
            'converted' => $converted,
            'conversion_rate' => $created > 0 ? round($converted / $created * 100, 1) : null,
            'by_status' => $byStatus,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function bySource(): array
    {
        return $this->scoped()
            ->whereBetween('leads.created_at', [$this->filters->from.' 00:00:00', $this->filters->to.' 23:59:59'])
            ->select('leads.source', DB::raw('COUNT(*) as total'))
            ->groupBy('leads.source')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['source' => $row->source ?? '—', 'total' => (int) $row->total])
            ->all();
    }

    /** @return array<int, array<string, mixed>> Top 10 responsables en alcance. */
    public function byOwner(): array
    {
        $rows = $this->scoped()
            ->whereBetween('leads.created_at', [$this->filters->from.' 00:00:00', $this->filters->to.' 23:59:59'])
            ->whereNotNull('leads.owner_id')
            ->select('leads.owner_id', DB::raw('COUNT(*) as total'))
            ->groupBy('leads.owner_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $names = User::whereIn('id', $rows->pluck('owner_id')->all())->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'owner_id' => $row->owner_id,
            'name' => $names[$row->owner_id] ?? 'Sin responsable',
            'total' => (int) $row->total,
        ])->all();
    }

    /** @return array<string, int> Buckets 0–24/25–49/50–74/75–100. */
    public function scoreDistribution(): array
    {
        $driver = DB::connection()->getDriverName();
        $score = $driver === 'sqlite' ? 'CAST(leads.score AS INTEGER)' : 'leads.score';

        $rows = $this->scoped()
            ->whereBetween('leads.created_at', [$this->filters->from.' 00:00:00', $this->filters->to.' 23:59:59'])
            ->whereNotNull('leads.score')
            ->select(DB::raw("CASE WHEN {$score} < 25 THEN '0–24' WHEN {$score} < 50 THEN '25–49' WHEN {$score} < 75 THEN '50–74' ELSE '75–100' END as bucket"), DB::raw('COUNT(*) as total'))
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->all();

        return [
            '0–24' => (int) ($rows['0–24'] ?? 0),
            '25–49' => (int) ($rows['25–49'] ?? 0),
            '50–74' => (int) ($rows['50–74'] ?? 0),
            '75–100' => (int) ($rows['75–100'] ?? 0),
        ];
    }

    private function scoped()
    {
        $query = Lead::visibleTo($this->user);

        if ($this->filters->ownerId !== null) {
            $query->where('leads.owner_id', $this->filters->ownerId);
        }

        return $query;
    }
}
