<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmImportRequest;
use App\Http\Requests\PreviewImportRequest;
use App\Http\Requests\UploadImportRequest;
use App\Models\DataImport;
use App\Services\Imports\ImportService;
use App\Support\ImportCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DataImportController extends Controller
{
    public function __construct(
        protected ImportService $importService = new ImportService,
    ) {}

    /**
     * Historial de importaciones visible según DataScope (scopeCreatedBy).
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', DataImport::class);

        $imports = DataImport::visibleTo($request->user())
            ->with('creator:id,name')
            ->withCount('errors')
            ->latest()
            ->paginate(15);

        return view('imports.index', [
            'imports' => $imports,
        ]);
    }

    /**
     * Paso 1 del asistente: selección de módulo y carga de archivo CSV.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', DataImport::class);

        $user = $request->user();
        $allowedModules = [];

        foreach (ImportCatalog::MODULES as $mod) {
            if ($user->can('create', [DataImport::class, $mod])) {
                $allowedModules[$mod] = ImportCatalog::label($mod);
            }
        }

        return view('imports.create', [
            'modules' => $allowedModules,
        ]);
    }

    /**
     * Descarga de plantilla CSV oficial con cabeceras y fila de ejemplo.
     */
    public function template(Request $request, string $module): Response
    {
        if (! in_array($module, ImportCatalog::MODULES, true)) {
            abort(404, 'Módulo no admitido.');
        }

        $this->authorize('create', [DataImport::class, $module]);

        $csv = ImportCatalog::templateCsv($module);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"plantilla_{$module}.csv\"",
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Procesa la subida del archivo y muestra la pantalla de mapeo de columnas.
     */
    public function upload(UploadImportRequest $request): View
    {
        $module = $request->input('module');
        $stored = $this->importService->storeTempFile($request->file('file'), $module);
        $targetFields = ImportCatalog::fields($module);

        return view('imports.mapping', [
            'module' => $module,
            'moduleLabel' => ImportCatalog::label($module),
            'tempPath' => $stored['temp_path'],
            'originalFilename' => $stored['original_filename'],
            'headers' => $stored['headers'],
            'targetFields' => $targetFields,
            'totalRows' => $stored['total_rows'],
        ]);
    }

    /**
     * Genera la previsualización de hasta 20 filas con validación preliminar.
     */
    public function preview(PreviewImportRequest $request): View
    {
        $module = $request->input('module');
        $tempPath = $request->input('temp_path');
        $originalFilename = $request->input('original_filename');
        $mapping = $request->input('mapping');

        $preview = $this->importService->generatePreview(
            $module,
            $tempPath,
            $originalFilename,
            $mapping,
            $request->user()
        );

        return view('imports.preview', [
            'module' => $module,
            'moduleLabel' => ImportCatalog::label($module),
            'tempPath' => $tempPath,
            'originalFilename' => $originalFilename,
            'previewRows' => $preview['preview_rows'],
            'totalRows' => $preview['total_rows'],
            'validCount' => $preview['valid_count'],
            'errorCount' => $preview['error_count'],
            'token' => $preview['token'],
            'tokenExpiresAt' => $preview['token_expires_at'],
        ]);
    }

    /**
     * Confirmación atómica e idempotente de la importación.
     */
    public function confirm(ConfirmImportRequest $request): RedirectResponse
    {
        $token = $request->input('token');

        try {
            $import = $this->importService->confirmImport($token, $request->user());

            return redirect()->route('imports.show', $import)
                ->with('status', "Importación finalizada. Filas exitosas: {$import->successful_rows}, Filas con error: {$import->failed_rows}.");
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('imports.index')
                ->withErrors(['token' => $e->getMessage()]);
        }
    }

    /**
     * Detalle de una importación con resumen de filas y errores.
     */
    public function show(DataImport $import): View
    {
        $this->authorize('view', $import);

        $import->load(['creator:id,name', 'errors']);

        return view('imports.show', [
            'import' => $import,
        ]);
    }

    /**
     * Descarga del archivo CSV de errores con neutralización de fórmulas.
     */
    public function errors(DataImport $import): Response
    {
        $this->authorize('downloadErrors', $import);

        $csv = $this->importService->generateErrorsCsv($import);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"errores_importacion_{$import->id}.csv\"",
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
