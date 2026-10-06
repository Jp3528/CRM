<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\Opportunity;
use App\Services\Reports\ReportFilters;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);

        $user = auth()->user();

        $cards = [
            ['key' => 'sales', 'label' => 'Ventas', 'route' => 'reports.sales', 'can' => 'sales.view', 'desc' => 'Confirmadas y completadas, por moneda.'],
            ['key' => 'pipeline', 'label' => 'Pipeline', 'route' => 'reports.pipeline', 'can' => 'opportunities.view', 'desc' => 'Oportunidades abiertas y etapas.'],
            ['key' => 'leads', 'label' => 'Leads', 'route' => 'reports.leads', 'can' => 'leads.view', 'desc' => 'Creación, calificación y conversión.'],
            ['key' => 'quotes', 'label' => 'Cotizaciones', 'route' => 'reports.quotes', 'can' => 'quotes.view', 'desc' => 'Estados y tasa de aceptación.'],
            ['key' => 'invoices', 'label' => 'Facturación interna', 'route' => 'reports.invoices', 'can' => 'invoices.view', 'desc' => 'Documentos internos, no fiscales.'],
            ['key' => 'support', 'label' => 'Soporte', 'route' => 'reports.support', 'can' => 'tickets.view', 'desc' => 'Tickets y tiempos medios.'],
            ['key' => 'campaigns', 'label' => 'Campañas', 'route' => 'reports.campaigns', 'can' => 'campaigns.view', 'desc' => 'Miembros y comunicaciones simuladas.'],
            ['key' => 'automations', 'label' => 'Automatizaciones', 'route' => 'reports.automations', 'can' => 'automations.view', 'desc' => 'Ejecuciones y tasa de éxito.'],
        ];

        $visible = array_values(array_filter(
            $cards,
            fn ($card) => $user->hasPermission($card['can'])
        ));

        return view('reports.index', [
            'cards' => $visible,
            'canForecast' => $user->hasPermission('reports.forecast')
                && $user->can('viewAny', Opportunity::class),
        ]);
    }

    /** Resuelve filtros o redirige con errores de validación. */
    protected function filters(ReportFilterRequest $request): ReportFilters
    {
        return ReportFilters::resolve($request->validated(), $request->user());
    }
}
