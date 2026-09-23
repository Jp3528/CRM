<?php

namespace App\Services\Reports;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Facturación INTERNA (documentos no fiscales). Por issue_date en rango.
 *
 * outstanding = total no paid ni cancelled (sin ledger de pagos parciales:
 * paid es marca, no monto). overdue efectivo = due_date vencida y pendiente
 * de pago (misma lógica que Invoice::is_overdue).
 */
final class InvoiceReportService
{
    public function __construct(private User $user, private ReportFilters $filters) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $inRange = fn () => $this->scoped()
            ->whereBetween('invoices.issue_date', [$this->filters->from, $this->filters->to]);

        $byStatus = (clone $inRange())
            ->select('invoices.status', DB::raw('COUNT(*) as total'))
            ->groupBy('invoices.status')
            ->pluck('total', 'status')
            ->all();

        $sumBy = function (callable $constraint) use ($inRange) {
            $q = $inRange();
            $constraint($q);

            return $q->select('invoices.currency', DB::raw('SUM(invoices.total) as total'))
                ->groupBy('invoices.currency')->pluck('total', 'currency')
                ->map(fn ($v) => (string) $v)->all();
        };

        $invoiced = $sumBy(fn ($q) => $q->whereNotIn('invoices.status', ['cancelled']));
        $paid = $sumBy(fn ($q) => $q->where('invoices.status', 'paid'));
        $outstanding = $sumBy(fn ($q) => $q->whereNotIn('invoices.status', ['paid', 'cancelled']));

        $overdue = (clone $inRange())
            ->whereNotNull('invoices.due_date')
            ->whereDate('invoices.due_date', '<', today()->toDateString())
            ->whereNotIn('invoices.status', ['paid', 'cancelled'])
            ->count();

        return [
            'issued' => array_sum(array_map('intval', $byStatus)),
            'by_status' => $byStatus,
            'overdue' => $overdue,
            'invoiced_by_currency' => $invoiced,
            'paid_by_currency' => $paid,
            'outstanding_by_currency' => $outstanding,
        ];
    }

    private function scoped()
    {
        $query = Invoice::visibleTo($this->user);

        if ($this->filters->ownerId !== null) {
            $query->where('invoices.owner_id', $this->filters->ownerId);
        }

        return $query;
    }
}
