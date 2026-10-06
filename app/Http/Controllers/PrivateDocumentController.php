<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Opportunity;
use App\Models\PrivateDocument;
use App\Models\Ticket;
use App\Services\Audit\AuditService;
use App\Services\Documents\DocumentSecurityValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateDocumentController extends Controller
{
    public const ALLOWED_TYPES = [
        'company' => Company::class,
        'opportunity' => Opportunity::class,
        'ticket' => Ticket::class,
    ];

    public function __construct(
        protected DocumentSecurityValidator $validator = new DocumentSecurityValidator,
        protected AuditService $auditService = new AuditService,
    ) {}

    /**
     * Sube y asocia un documento privado a una entidad permitida.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Verificar permiso funcional de documentos
        abort_unless($user->hasPermission('documents.create'), 403, 'No tienes permiso para subir documentos privados.');

        $validated = $request->validate([
            'documentable_type' => ['required', 'string', 'in:company,opportunity,ticket'],
            'documentable_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'max:10240'], // 10 MB
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $typeKey = $validated['documentable_type'];
        $modelClass = self::ALLOWED_TYPES[$typeKey];
        $entity = $modelClass::findOrFail($validated['documentable_id']);

        // Requiere permiso de edición sobre la entidad padre
        $this->authorize('update', $entity);

        // Límite acotado de adjuntos por entidad
        if ($entity->documents()->count() >= 20) {
            return back()->withErrors(['file' => 'Se ha alcanzado el límite máximo de 20 documentos para este registro.']);
        }

        // Validación estricta de seguridad
        $file = $request->file('file');
        $meta = $this->validator->validate($file);

        // Generar ruta interna no predecible
        $uuid = (string) Str::uuid();
        $storedName = "{$uuid}.{$meta['extension']}";
        $relativePath = "documents/{$typeKey}/{$storedName}";

        // Guardar archivo en disco privado
        Storage::disk('private')->putFileAs("documents/{$typeKey}", $file, $storedName);

        try {
            $document = PrivateDocument::create([
                'user_id' => $user->id,
                'documentable_type' => $entity->getMorphClass(),
                'documentable_id' => $entity->id,
                'original_name' => $meta['original_name'],
                'file_path' => $relativePath,
                'mime_type' => $meta['mime_type'],
                'file_size' => $meta['file_size'],
                'description' => $validated['description'] ?? null,
            ]);

            $this->auditService->log(
                $user,
                $document,
                'document.uploaded',
                null,
                [
                    'original_name' => $document->original_name,
                    'file_size' => $document->file_size,
                    'documentable_type' => $document->documentable_type,
                    'documentable_id' => $document->documentable_id,
                ]
            );
        } catch (\Throwable $e) {
            // Compensación: eliminar archivo huérfano si falla la base de datos
            Storage::disk('private')->delete($relativePath);
            throw $e;
        }

        return back()->with('status', "Documento '{$meta['original_name']}' adjuntado correctamente.");
    }

    /**
     * Descarga de forma segura un documento privado comprobando permisos vigentes.
     */
    public function download(Request $request, PrivateDocument $document): StreamedResponse
    {
        $user = $request->user();

        // 1. Permiso funcional
        abort_unless($user->hasPermission('documents.view'), 403, 'No tienes permiso para consultar documentos adjuntos.');

        // 2. Entidad padre existente y autorizada
        $entity = $document->documentable;
        if (! $entity || (method_exists($entity, 'trashed') && $entity->trashed())) {
            abort(404, 'El registro asociado a este documento no está disponible.');
        }

        // Debe tener permiso de lectura sobre la entidad padre (aplica DataScope automáticamente)
        $this->authorize('view', $entity);

        // 3. Comprobar existencia en almacenamiento sin filtrar rutas del servidor
        if (! Storage::disk('private')->exists($document->file_path)) {
            abort(404, 'El archivo solicitado no se encuentra en el almacenamiento.');
        }

        $sanitizedDownloadName = addcslashes($document->original_name, '"\\');

        return Storage::disk('private')->download(
            $document->file_path,
            $document->original_name,
            [
                'Content-Type' => $document->mime_type,
                'Content-Disposition' => "attachment; filename=\"{$sanitizedDownloadName}\"",
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'no-cache, private',
            ]
        );
    }

    /**
     * Elimina un documento privado comprobando permisos vigentes.
     */
    public function destroy(Request $request, PrivateDocument $document): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->hasPermission('documents.delete'), 403, 'No tienes permiso para eliminar documentos adjuntos.');

        $entity = $document->documentable;
        if ($entity) {
            $this->authorize('update', $entity);
        }

        $this->auditService->log(
            $user,
            $document,
            'document.deleted',
            [
                'original_name' => $document->original_name,
                'file_path' => $document->file_path,
                'documentable_type' => $document->documentable_type,
                'documentable_id' => $document->documentable_id,
            ],
            null
        );

        // Eliminar del almacenamiento
        if (Storage::disk('private')->exists($document->file_path)) {
            Storage::disk('private')->delete($document->file_path);
        }

        $document->delete();

        return back()->with('status', "Documento '{$document->original_name}' eliminado.");
    }
}
