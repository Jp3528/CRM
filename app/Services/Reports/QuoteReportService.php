<?php

namespace App\Services\Reports;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cotizaciones por issue_date en rango. expired es calculado (draft/sent con
 * valid_until vencida), respetando el diseño real.
 *
 * acceptance_rate = accepted / (accepted + rejected). Los enviados sin
 * respuesta no cuentan como decisión. Definición única y documentada.
 */
final class QuoteReportService
{
    public function __construct(private User $user, private ReportFilters $filters) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $inRange = fn () => $this->scoped()
            ->whereBetween('quotes.issue_date', [$this->filters->from, $this->filters->to]);

        $byStatus = (clone $inRange())
            ->select('quotes.status', DB::raw('COUNT(*) as total'))
            ->groupBy('quotes.status')
            ->pluck('total', 'status')
            ->all();

        $accepted = (int) ($byStatus['accepted'] ?? 0);
        $rejected = (int) ($byStatus['rejected'] ?? 0);
        $decided = $accepted + $rejected;

        $expired = (clone $inRange())
            ->whereIn('quotes.status', ['draft', 'sent'])
            ->whereNotNull('quotes.valid_until')
            ->whereDate('quotes.valid_until', '<', today()->toDateString())
            ->count();

        $quoted = (clone $inRange())
            ->select('quotes.currency', DB::raw('SUM(quotes.total) as total'))
            ->groupBy('quotes.currency')->pluck('total', 'currency')
            ->map(fn ($v) => (string) $v)->all();

        $acceptedValue = (clone $inRange())->where('quotes.status', 'accepted')
            ->select('quotes.currency', DB::raw('SUM(quotes.total) as total'))
            ->groupBy('quotes.currency')->pluck('total', 'currency')
            ->map(fn ($v) => (string) $v)->all();

        return [
            'created' => array_sum(array_map('intval', $byStatus)),
            'by_status' => $byStatus,
            'expired_computed' => $expired,
            'acceptance_rate' => $decided > 0 ? round($accepted / $decided * 100, 1) : null,
            'quoted_by_currency' => $quoted,
            'accepted_by_currency' => $acceptedValue,
        ];
    }

    private function scoped()
    {
        $query = Quote::visibleTo($this->user);

        if ($this->filters->ownerId !== null) {
            $query->where('quotes.owner_id', $this->filters->ownerId);
        }

        return $query;
    }
}
