<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GlobalSearchController extends Controller
{
    /**
     * Búsqueda global autorizada y acotada por DataScope y permisos de módulo.
     */
    public function search(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $query = trim($request->string('q')->toString());

        $results = [
            'companies' => collect(),
            'contacts' => collect(),
            'leads' => collect(),
            'opportunities' => collect(),
            'tickets' => collect(),
        ];

        $totalCount = 0;

        if (mb_strlen($query) >= 2) {
            $term = mb_strtolower($query);

            // 1. Empresas
            if ($user->can('companies.view')) {
                $results['companies'] = Company::visibleTo($user)
                    ->where(function ($q) use ($term) {
                        $q->whereRaw('LOWER(trade_name) LIKE ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(legal_name) LIKE ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(tax_id) LIKE ?', ["%{$term}%"]);
                    })
                    ->orderBy('trade_name')
                    ->take(5)
                    ->get(['id', 'trade_name', 'tax_id', 'status', 'owner_id']);

                $totalCount += $results['companies']->count();
            }

            // 2. Contactos
            if ($user->can('contacts.view')) {
                $results['contacts'] = Contact::visibleTo($user)
                    ->with('company:id,trade_name')
                    ->where(function ($q) use ($term) {
                        $q->whereRaw('LOWER(first_name) LIKE ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(email) LIKE ?', ["%{$term}%"]);
                    })
                    ->orderBy('first_name')
                    ->take(5)
                    ->get(['id', 'first_name', 'last_name', 'email', 'company_id', 'owner_id']);

                $totalCount += $results['contacts']->count();
            }

            // 3. Leads
            if ($user->can('leads.view')) {
                $results['leads'] = Lead::visibleTo($user)
                    ->where(function ($q) use ($term) {
                        $q->whereRaw('LOWER(first_name) LIKE ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(company_name) LIKE ?', ["%{$term}%"]);
                    })
                    ->orderBy('first_name')
                    ->take(5)
                    ->get(['id', 'first_name', 'last_name', 'company_name', 'status', 'owner_id']);

                $totalCount += $results['leads']->count();
            }

            // 4. Oportunidades
            if ($user->can('opportunities.view')) {
                $results['opportunities'] = Opportunity::visibleTo($user)
                    ->with('stage:id,name')
                    ->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                    ->orderBy('name')
                    ->take(5)
                    ->get(['id', 'name', 'amount', 'currency', 'status', 'pipeline_stage_id', 'owner_id']);

                $totalCount += $results['opportunities']->count();
            }

            // 5. Tickets
            if ($user->can('tickets.view')) {
                $results['tickets'] = Ticket::visibleTo($user)
                    ->where(function ($q) use ($term) {
                        $q->whereRaw('LOWER(number) LIKE ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(subject) LIKE ?', ["%{$term}%"]);
                    })
                    ->orderBy('id', 'desc')
                    ->take(5)
                    ->get(['id', 'number', 'subject', 'status', 'priority', 'assigned_to', 'created_by']);

                $totalCount += $results['tickets']->count();
            }
        }

        if ($request->wantsJson()) {
            $formatted = [];
            foreach ($results['companies'] as $c) {
                $formatted[] = ['type' => 'Empresa', 'title' => $c->trade_name, 'meta' => $c->tax_id ?? 'Sin RUC/DNI', 'url' => route('companies.show', $c)];
            }
            foreach ($results['contacts'] as $co) {
                $formatted[] = ['type' => 'Contacto', 'title' => "{$co->first_name} {$co->last_name}", 'meta' => $co->company?->trade_name ?? $co->email, 'url' => route('contacts.show', $co)];
            }
            foreach ($results['leads'] as $l) {
                $formatted[] = ['type' => 'Lead', 'title' => "{$l->first_name} {$l->last_name}", 'meta' => $l->company_name ?? ucfirst($l->status), 'url' => route('leads.show', $l)];
            }
            foreach ($results['opportunities'] as $o) {
                $formatted[] = ['type' => 'Oportunidad', 'title' => $o->name, 'meta' => $o->stage?->name ?? number_format((float) $o->amount, 2).' '.$o->currency, 'url' => route('opportunities.show', $o)];
            }
            foreach ($results['tickets'] as $t) {
                $formatted[] = ['type' => 'Ticket', 'title' => "{$t->number}: {$t->subject}", 'meta' => ucfirst($t->status).' · '.ucfirst($t->priority), 'url' => route('tickets.show', $t)];
            }

            return response()->json([
                'query' => $query,
                'total' => $totalCount,
                'items' => $formatted,
            ]);
        }

        return view('search.index', [
            'query' => $query,
            'results' => $results,
            'totalCount' => $totalCount,
        ]);
    }
}
