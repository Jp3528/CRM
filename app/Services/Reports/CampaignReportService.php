<?php

namespace App\Services\Reports;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Communication;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Campañas: conteos y miembros visibles (scoped), comunicaciones
 * REGISTRADAS/SIMULADAS (nunca open rate/CTR/delivery), financieros como
 * montos de referencia (sin revenue generado: no hay atribución real).
 */
final class CampaignReportService
{
    public function __construct(private User $user, private ReportFilters $filters) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $byStatus = $this->scoped()
            ->select('campaigns.status', DB::raw('COUNT(*) as total'))
            ->groupBy('campaigns.status')
            ->pluck('total', 'status')
            ->map(fn ($v) => (int) $v)->all();

        $campaignIds = $this->scoped()->pluck('campaigns.id')->all();

        // Miembros con objetivo visible (sin fuga por agregados).
        $members = $campaignIds === [] ? collect() : CampaignMember::whereIn('campaign_id', $campaignIds)->get();
        $contactIds = $members->where('member_type', 'contact')->pluck('member_id')->unique()->all();
        $leadIds = $members->where('member_type', 'lead')->pluck('member_id')->unique()->all();
        $visibleContacts = $contactIds === [] ? [] : Contact::visibleTo($this->user)->whereIn('id', $contactIds)->pluck('id')->all();
        $visibleLeads = $leadIds === [] ? [] : Lead::visibleTo($this->user)->whereIn('id', $leadIds)->pluck('id')->all();

        $visibleMembers = $members->filter(fn ($m) => $m->member_type === 'contact'
            ? in_array($m->member_id, $visibleContacts, true)
            : in_array($m->member_id, $visibleLeads, true));

        $byMemberStatus = $visibleMembers->countBy('status')->map(fn ($v) => (int) $v)->all();

        $from = $this->filters->from.' 00:00:00';
        $to = $this->filters->to.' 23:59:59';
        $simulated = $campaignIds === [] ? 0 : Communication::visibleTo($this->user)
            ->whereIn('communications.campaign_id', $campaignIds)
            ->where('communications.status', 'simulated_sent')
            ->whereBetween('communications.sent_at', [$from, $to])
            ->count();

        $financials = $this->scoped()
            ->select(
                DB::raw('SUM(campaigns.budget) as budget'),
                DB::raw('SUM(campaigns.expected_revenue) as expected_revenue'),
                DB::raw('SUM(campaigns.actual_cost) as actual_cost')
            )->first();

        $budget = $financials->budget !== null ? (string) $financials->budget : null;
        $actual = $financials->actual_cost !== null ? (string) $financials->actual_cost : null;

        return [
            'total' => array_sum($byStatus),
            'by_status' => $byStatus,
            'visible_members' => $visibleMembers->count(),
            'members_by_status' => $byMemberStatus,
            'simulated_in_range' => $simulated,
            'budget' => $budget,
            'expected_revenue' => $financials->expected_revenue !== null ? (string) $financials->expected_revenue : null,
            'actual_cost' => $actual,
            'budget_utilization' => $budget !== null && bccomp($budget, '0', 2) > 0 && $actual !== null
                ? round((float) bcdiv($actual, $budget, 6) * 100, 1)
                : null,
        ];
    }

    private function scoped()
    {
        $query = Campaign::visibleTo($this->user);

        if ($this->filters->ownerId !== null) {
            $query->where('campaigns.owner_id', $this->filters->ownerId);
        }

        return $query;
    }
}
