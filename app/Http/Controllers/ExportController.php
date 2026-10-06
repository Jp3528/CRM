<?php

namespace App\Http\Controllers;

use App\Services\Exports\ExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public const MODULE_PERMISSIONS = [
        'companies' => 'companies.view',
        'contacts' => 'contacts.view',
        'leads' => 'leads.view',
        'opportunities' => 'opportunities.view',
        'tasks' => 'tasks.view',
        'quotes' => 'quotes.view',
        'sales' => 'sales.view',
        'invoices' => 'invoices.view',
        'tickets' => 'tickets.view',
    ];

    public function __construct(
        protected ExportService $exportService = new ExportService,
    ) {}

    /**
     * Exporta el listado de un módulo a CSV en streaming respetando DataScope y filtros.
     */
    public function export(Request $request, string $module): StreamedResponse
    {
        $user = $request->user();

        // Verificar permiso general de exportación
        abort_unless($user->hasPermission('exports.view'), 403, 'No tienes permiso para exportar datos del sistema.');

        // Verificar que el módulo sea admitido
        $requiredPerm = self::MODULE_PERMISSIONS[$module] ?? null;
        if (! $requiredPerm) {
            abort(404, "Módulo de exportación '{$module}' no encontrado.");
        }

        // Verificar permiso de lectura del módulo específico
        abort_unless($user->hasPermission($requiredPerm), 403, "No tienes permiso para ver o exportar '{$module}'.");

        return $this->exportService->exportModule($module, $user, $request->query());
    }
}
