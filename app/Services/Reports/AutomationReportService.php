<?php

namespace App\Services\Reports;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Automatizaciones visibles: activas + runs en rango.
 *
 * success_ratio = success / (success + failed). Skipped se excluye (no son
 * ejecuciones intentadas). Solo automations visibles afectan agregados.
 */
final class AutomationReportService
{
    public function __construct(private User $user, private ReportFilters $filters) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $visibleIds = $this->scoped()->pluck('automations.id')->all();

        $active = $this->scoped()->where('automations.status', 'active')->count();

        $from = $this->filters->from.' 00:00:00';
        $to = $this->filters->to.' 23:59:59';

        $byStatus = $visibleIds === [] ? [] : AutomationRun::whereIn('automation_id', $visibleIds)
            ->whereBetween('automation_runs.created_at', [$from, $to])
            ->select('automation_runs.status', DB::raw('COUNT(*) as total'))
            ->groupBy('automation_runs.status')
            ->pluck('total', 'status')
            ->map(fn ($v) => (int) $v)->all();

        $success = (int) ($byStatus['success'] ?? 0);
        $failed = (int) ($byStatus['failed'] ?? 0);
        $executable = $success + $failed;

        $driver = DB::connection()->getDriverName();
        $dayExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', automation_runs.created_at)"
            : "TO_CHAR(automation_runs.created_at, 'YYYY-MM-DD')";

        $trend = $visibleIds === [] ? [] : AutomationRun::whereIn('automation_id', $visibleIds)
            ->whereBetween('automation_runs.created_at', [$from, $to])
            ->select(DB::raw("{$dayExpr} as day"), 'automation_runs.status', DB::raw('COUNT(*) as total'))
            ->groupBy('day', 'automation_runs.status')
            ->orderBy('day')
            ->limit(500)
            ->get()
            ->groupBy('day')
            ->map(fn ($rows, $day) => [
                'label' => $day,
                'totals' => $rows->pluck('total', 'status')->map(fn ($v) => (int) $v)->all(),
            ])->values()->all();

        $top = $visibleIds === [] ? [] : AutomationRun::whereIn('automation_id', $visibleIds)
            ->whereBetween('automation_runs.created_at', [$from, $to])
            ->select('automation_runs.automation_id', DB::raw('COUNT(*) as runs'), DB::raw("SUM(CASE WHEN automation_runs.status = 'failed' THEN 1 ELSE 0 END) as failures"))
            ->groupBy('automation_runs.automation_id')
            ->orderByDesc('runs')
            ->limit(10)
            ->get();

        $names = $top === [] ? collect() : Automation::whereIn('id', $top->pluck('automation_id')->all())->pluck('name', 'id');

        return [
            'active' => $active,
            'by_status' => $byStatus,
            'total_runs' => array_sum($byStatus),
            'success_ratio' => $executable > 0 ? round($success / $executable * 100, 1) : null,
            'trend' => $trend,
            'top' => collect($top)->map(fn ($row) => [
                'automation_id' => $row->automation_id,
                'name' => $names[$row->automation_id] ?? '—',
                'runs' => (int) $row->runs,
                'failures' => (int) $row->failures,
            ])->all(),
        ];
    }

    private function scoped()
    {
        $query = Automation::visibleTo($this->user);

        if ($this->filters->ownerId !== null) {
            $query->where('automations.owner_id', $this->filters->ownerId);
        }

        return $query;
    }
}
