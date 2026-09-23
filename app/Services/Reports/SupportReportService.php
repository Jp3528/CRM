<?php

namespace App\Services\Reports;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Soporte. Conteos de flujo por created_at/resolved_at en rango; estados
 * (open/pending/urgent/unassigned) como snapshot actual scoped —NO se finge
 * evolución histórica—. Tiempos solo con timestamps reales; sin "SLA".
 */
final class SupportReportService
{
    public function __construct(private User $user, private ReportFilters $filters) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $from = $this->filters->from.' 00:00:00';
        $to = $this->filters->to.' 23:59:59';

        $created = $this->scoped()->whereBetween('tickets.created_at', [$from, $to])->count();

        $resolved = $this->scoped()->whereNotNull('tickets.resolved_at')
            ->whereBetween('tickets.resolved_at', [$from, $to])->count();

        // Snapshot actual (alcance), no histórico reconstruido.
        $snapshot = fn () => $this->scoped();
        $open = (clone $snapshot())->whereIn('tickets.status', ['new', 'open'])->count();
        $pending = (clone $snapshot())->where('tickets.status', 'pending')->count();
        $urgent = (clone $snapshot())->where('tickets.priority', 'urgent')
            ->whereNotIn('tickets.status', ['resolved', 'closed'])->count();
        $unassigned = (clone $snapshot())->whereNull('tickets.assigned_to')
            ->whereNotIn('tickets.status', ['resolved', 'closed'])->count();

        $driver = DB::connection()->getDriverName();
        $epochDiff = fn (string $fromCol, string $toCol) => $driver === 'sqlite'
            ? "(strftime('%s', tickets.{$toCol}) - strftime('%s', tickets.{$fromCol}))"
            : "EXTRACT(EPOCH FROM (tickets.{$toCol} - tickets.{$fromCol}))";

        $avgFirst = $this->scoped()->whereNotNull('tickets.first_response_at')
            ->whereBetween('tickets.created_at', [$from, $to])
            ->select(DB::raw("AVG({$epochDiff('created_at', 'first_response_at')}) as avg_seconds"))
            ->value('avg_seconds');

        $avgResolution = $this->scoped()->whereNotNull('tickets.resolved_at')
            ->whereBetween('tickets.resolved_at', [$from, $to])
            ->select(DB::raw("AVG({$epochDiff('created_at', 'resolved_at')}) as avg_seconds"))
            ->value('avg_seconds');

        return [
            'created' => $created,
            'resolved' => $resolved,
            'open_snapshot' => $open,
            'pending_snapshot' => $pending,
            'urgent_snapshot' => $urgent,
            'unassigned_snapshot' => $unassigned,
            'avg_first_response_seconds' => $avgFirst !== null ? (float) $avgFirst : null,
            'avg_resolution_seconds' => $avgResolution !== null ? (float) $avgResolution : null,
        ];
    }

    /** @return array<string, array<string, int>> */
    public function breakdowns(): array
    {
        $from = $this->filters->from.' 00:00:00';
        $to = $this->filters->to.' 23:59:59';
        $inRange = fn () => $this->scoped()->whereBetween('tickets.created_at', [$from, $to]);

        $byStatus = (clone $inRange())->select('tickets.status', DB::raw('COUNT(*) as total'))
            ->groupBy('tickets.status')->pluck('total', 'status')
            ->map(fn ($v) => (int) $v)->all();

        $byPriority = (clone $inRange())->select('tickets.priority', DB::raw('COUNT(*) as total'))
            ->groupBy('tickets.priority')->pluck('total', 'priority')
            ->map(fn ($v) => (int) $v)->all();

        $byCategory = (clone $inRange())->select('tickets.category_id', DB::raw('COUNT(*) as total'))
            ->groupBy('tickets.category_id')->get();
        $catNames = TicketCategory::whereIn('id', $byCategory->pluck('category_id')->filter()->all())->pluck('name', 'id');
        $categories = [];
        foreach ($byCategory as $row) {
            $categories[$row->category_id === null ? 'none' : $catNames[$row->category_id] ?? '—'] = (int) $row->total;
        }

        $byAssignee = (clone $inRange())->select('tickets.assigned_to', DB::raw('COUNT(*) as total'))
            ->groupBy('tickets.assigned_to')->get();
        $userNames = User::whereIn('id', $byAssignee->pluck('assigned_to')->filter()->all())->pluck('name', 'id');
        $assignees = [];
        foreach ($byAssignee as $row) {
            $assignees[$row->assigned_to === null ? 'Sin asignar' : ($userNames[$row->assigned_to] ?? '—')] = (int) $row->total;
        }

        return [
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
            'by_category' => $categories,
            'by_assignee' => $assignees,
        ];
    }

    private function scoped()
    {
        // Alcance de soporte (propios + cola según rol). Filtro owner → assigned_to.
        $query = Ticket::visibleTo($this->user);

        if ($this->filters->ownerId !== null) {
            $query->where('tickets.assigned_to', $this->filters->ownerId);
        }

        return $query;
    }
}
