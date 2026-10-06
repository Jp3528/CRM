<?php

namespace App\Services\Reports;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Campaign;
use App\Models\Communication;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Support\DataScope;
use App\Support\ReportFormat;
use Illuminate\Support\Facades\DB;

/**
 * KPIs ejecutivos del dashboard (respetan permiso del módulo + DataScope).
 * Comparte definiciones con los reportes (conversion, acceptance, ponderado).
 */
final class ExecutiveDashboardService
{
    public function __construct(private User $user, private ReportFilters $filters) {}

    /** @return array<string, mixed> */
    public function build(): array
    {
        $user = $this->user;

        return [
            'pipeline' => $user->can('viewAny', Opportunity::class) ? $this->pipeline() : null,
            'won' => $user->can('viewAny', Opportunity::class) ? $this->won() : null,
            'sales' => $user->can('viewAny', Sale::class) ? $this->sales() : null,
            'invoices' => $user->can('viewAny', Invoice::class) ? $this->invoices() : null,
            'leads' => $user->can('viewAny', Lead::class) ? $this->leads() : null,
            'quotes' => $user->can('viewAny', Quote::class) ? $this->quotes() : null,
            'tickets' => $user->can('viewAny', Ticket::class) ? $this->tickets() : null,
            'campaigns' => $user->can('viewAny', Campaign::class) ? $this->campaigns() : null,
            'automations' => $user->can('viewAny', Automation::class) ? $this->automations() : null,
            'tasks' => $user->can('viewAny', Task::class) ? $this->tasks() : null,
            'charts' => $this->charts(),
        ];
    }

    /** @return array<string, mixed> */
    private function pipeline(): array
    {
        $rows = $this->oppScoped()->where('opportunities.status', 'open')
            ->select('opportunities.currency', DB::raw('COUNT(*) as deals'), DB::raw('SUM(opportunities.amount) as amount'), DB::raw('SUM(opportunities.amount * opportunities.probability / 100) as weighted'))
            ->groupBy('opportunities.currency')->orderBy('opportunities.currency')->get();

        return [
            'deals' => $rows->sum('deals'),
            'by_currency' => $rows->mapWithKeys(fn ($r) => [$r->currency => [
                'amount' => $r->amount !== null ? (string) $r->amount : null,
                'weighted' => $r->weighted !== null ? (string) $r->weighted : null,
            ]])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function won(): array
    {
        $inRange = $this->oppScoped()->where('opportunities.status', 'won')
            ->whereNotNull('opportunities.actual_close_date')
            ->whereDate('opportunities.actual_close_date', '>=', $this->filters->from)
            ->whereDate('opportunities.actual_close_date', '<=', $this->filters->to);

        $rows = (clone $inRange)
            ->select('opportunities.currency', DB::raw('COUNT(*) as deals'), DB::raw('SUM(opportunities.amount) as amount'))
            ->groupBy('opportunities.currency')->orderBy('opportunities.currency')->get();

        [$prevFrom, $prevTo] = $this->filters->previousPeriod();
        $prev = $this->oppScoped()->where('opportunities.status', 'won')
            ->whereNotNull('opportunities.actual_close_date')
            ->whereDate('opportunities.actual_close_date', '>=', $prevFrom)
            ->whereDate('opportunities.actual_close_date', '<=', $prevTo)
            ->select('opportunities.currency', DB::raw('SUM(opportunities.amount) as amount'))
            ->groupBy('opportunities.currency')->pluck('amount', 'currency');

        return [
            'deals' => $rows->sum('deals'),
            'by_currency' => $rows->mapWithKeys(fn ($r) => [$r->currency => [
                'amount' => $r->amount !== null ? (string) $r->amount : null,
                'trend' => ReportFormat::trend(
                    $r->amount !== null ? (float) $r->amount : null,
                    isset($prev[$r->currency]) ? (float) $prev[$r->currency] : null
                ),
            ]])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function sales(): array
    {
        $service = new SalesReportService($this->user, $this->filters);

        return $service->summary();
    }

    /** @return array<string, mixed> */
    private function invoices(): array
    {
        $service = new InvoiceReportService($this->user, $this->filters);
        $summary = $service->summary();

        return [
            'invoiced_by_currency' => $summary['invoiced_by_currency'],
            'paid_by_currency' => $summary['paid_by_currency'],
            'outstanding_by_currency' => $summary['outstanding_by_currency'],
            'overdue' => $summary['overdue'],
        ];
    }

    /** @return array<string, mixed> */
    private function leads(): array
    {
        $service = new LeadReportService($this->user, $this->filters);

        return $service->summary();
    }

    /** @return array<string, mixed> */
    private function quotes(): array
    {
        $service = new QuoteReportService($this->user, $this->filters);
        $summary = $service->summary();

        return [
            'created' => $summary['created'],
            'by_status' => $summary['by_status'],
            'acceptance_rate' => $summary['acceptance_rate'],
        ];
    }

    /** @return array<string, mixed> */
    private function tickets(): array
    {
        $service = new SupportReportService($this->user, $this->filters);
        $summary = $service->summary();

        return [
            'created' => $summary['created'],
            'resolved' => $summary['resolved'],
            'open_snapshot' => $summary['open_snapshot'],
            'pending_snapshot' => $summary['pending_snapshot'],
            'urgent_snapshot' => $summary['urgent_snapshot'],
            'avg_first_response_seconds' => $summary['avg_first_response_seconds'],
            'avg_resolution_seconds' => $summary['avg_resolution_seconds'],
        ];
    }

    /** @return array<string, mixed> */
    private function campaigns(): array
    {
        $active = Campaign::visibleTo($this->user)->where('campaigns.status', 'active')->count();
        $simulated = Communication::visibleTo($this->user)
            ->where('communications.status', 'simulated_sent')
            ->whereBetween('communications.sent_at', [$this->filters->from.' 00:00:00', $this->filters->to.' 23:59:59'])
            ->count();

        return ['active' => $active, 'simulated_in_range' => $simulated];
    }

    /** @return array<string, mixed> */
    private function automations(): array
    {
        $active = Automation::visibleTo($this->user)->where('automations.status', 'active')->count();
        $visibleIds = Automation::visibleTo($this->user)->select('automations.id');
        $byStatus = AutomationRun::whereIn('automation_id', $visibleIds)
            ->whereBetween('automation_runs.created_at', [$this->filters->from.' 00:00:00', $this->filters->to.' 23:59:59'])
            ->select('automation_runs.status', DB::raw('COUNT(*) as total'))
            ->groupBy('automation_runs.status')->pluck('total', 'status')
            ->map(fn ($v) => (int) $v)->all();

        return ['active' => $active, 'by_status' => $byStatus];
    }

    /** @return array<string, int> */
    private function tasks(): array
    {
        $base = fn () => DataScope::visibleRecords($this->user, Task::class);
        $assignee = $this->filters->ownerId ?? $this->user->id;
        $assigned = fn () => (clone $base())->where('tasks.assigned_to', $assignee);

        return [
            'pending' => (clone $assigned())->where('tasks.status', 'pending')->count(),
            'overdue' => (clone $assigned())->where('tasks.due_at', '<', now())->whereNotIn('tasks.status', ['completed', 'cancelled'])->count(),
            'completed_in_range' => (clone $assigned())->where('tasks.status', 'completed')
                ->whereBetween('tasks.completed_at', [$this->filters->from.' 00:00:00', $this->filters->to.' 23:59:59'])->count(),
        ];
    }

    /** @return array<string, mixed> Mini-charts del dashboard. */
    private function charts(): array
    {
        $user = $this->user;

        $salesTrend = $user->can('viewAny', Sale::class)
            ? (new SalesReportService($user, $this->filters))->trend()
            : [];

        $pipelineStages = $user->can('viewAny', Opportunity::class)
            ? (new PipelineReportService($user, $this->filters))->byStage()
            : [];

        $leadsBySource = $user->can('viewAny', Lead::class)
            ? (new LeadReportService($user, $this->filters))->bySource()
            : [];

        $ticketsByStatus = $user->can('viewAny', Ticket::class)
            ? (new SupportReportService($user, $this->filters))->breakdowns()['by_status']
            : [];

        return [
            'sales_trend' => array_slice($salesTrend, -12),
            'pipeline_stages' => $pipelineStages,
            'leads_by_source' => $leadsBySource,
            'tickets_by_status' => $ticketsByStatus,
        ];
    }

    private function oppScoped()
    {
        $query = Opportunity::visibleTo($this->user);

        if ($this->filters->ownerId !== null) {
            $query->where('opportunities.owner_id', $this->filters->ownerId);
        }

        return $query;
    }
}
