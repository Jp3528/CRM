@props([
    'entity',
    'type',
    'canUpload' => false,
    'canDelete' => false,
])

@php
    $documents = $entity->documents()->with('user:id,name')->get();
@endphp

<div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs space-y-5">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h3 class="text-base font-semibold text-slate-900">Documentos y Adjuntos Privados</h3>
            <p class="text-xs text-slate-500">Archivos restringidos almacenados de forma privada con descarga autorizada.</p>
        </div>
        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 font-mono">
            {{ $documents->count() }} / 20
        </span>
    </div>

    {{-- Formulario de carga --}}
    @if ($canUpload && $documents->count() < 20)
        <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="bg-slate-50 p-4 rounded-lg border border-slate-200/80 space-y-3">
            @csrf
            <input type="hidden" name="documentable_type" value="{{ $type }}">
            <input type="hidden" name="documentable_id" value="{{ $entity->id }}">

            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                <div class="sm:col-span-5">
                    <label class="block text-xs font-semibold uppercase text-slate-700 tracking-wider mb-1">Seleccionar archivo</label>
                    <input type="file" name="file" required accept=".pdf,.png,.jpg,.jpeg,.xlsx"
                           class="w-full text-xs text-slate-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-cyan-700 file:text-white hover:file:bg-cyan-800 cursor-pointer">
                </div>

                <div class="sm:col-span-5">
                    <label class="block text-xs font-semibold uppercase text-slate-700 tracking-wider mb-1">Descripción (opcional)</label>
                    <input type="text" name="description" placeholder="Ej. Contrato firmado, orden de compra..."
                           class="w-full rounded-md border border-slate-300 px-3 py-1.5 text-xs text-slate-800 focus:border-cyan-500 focus:ring-cyan-500">
                </div>

                <div class="sm:col-span-2">
                    <button type="submit" class="w-full inline-flex items-center justify-center rounded-md bg-cyan-700 px-3 py-2 text-xs font-semibold text-white shadow-xs hover:bg-cyan-800 transition-colors">
                        Adjuntar
                    </button>
                </div>
            </div>

            <p class="text-[11px] text-slate-500">
                Formatos permitidos: <strong>PDF, PNG, JPEG, XLSX</strong>. Máx. 10 MB. Almacenamiento protegido sin enlace público.
            </p>
        </form>
    @endif

    {{-- Listado de documentos adjuntos --}}
    @if ($documents->isNotEmpty())
        <div class="divide-y divide-slate-100 border border-slate-200 rounded-lg overflow-hidden">
            @foreach ($documents as $doc)
                <div class="p-3.5 flex items-center justify-between gap-4 hover:bg-slate-50 transition-colors">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg
                            @if(str_contains($doc->mime_type, 'pdf')) bg-rose-100 text-rose-700
                            @elseif(str_contains($doc->mime_type, 'sheet') || str_contains($doc->original_name, '.xlsx')) bg-emerald-100 text-emerald-700
                            @else bg-sky-100 text-sky-700 @endif text-xs font-bold uppercase">
                            {{ pathinfo($doc->original_name, PATHINFO_EXTENSION) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-xs sm:text-sm text-slate-900 truncate" title="{{ $doc->original_name }}">
                                    {{ $doc->original_name }}
                                </span>
                                <span class="text-[11px] font-mono text-slate-500 shrink-0">({{ $doc->formatted_size }})</span>
                            </div>
                            <div class="flex items-center gap-2 text-[11px] text-slate-500 mt-0.5">
                                <span>Cargado por {{ $doc->user?->name ?? 'Sistema' }}</span>
                                <span>•</span>
                                <span>{{ $doc->created_at?->format('d/m/Y H:i') }}</span>
                                @if ($doc->description)
                                    <span>•</span>
                                    <span class="italic truncate text-slate-600">"{{ $doc->description }}"</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route('documents.download', $doc) }}"
                           class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-cyan-700 transition-colors">
                            Descargar
                        </a>

                        @if ($canDelete)
                            <form method="POST" action="{{ route('documents.destroy', $doc) }}" onsubmit="return confirm('¿Eliminar este documento adjunto permanentemente?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-md p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Eliminar archivo">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-6 text-slate-400 text-xs">
            No hay documentos privados adjuntos a este registro.
        </div>
    @endif
</div>
