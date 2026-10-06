@extends('layouts.app', ['header' => 'Configuración del sistema', 'subheader' => 'Parámetros del negocio, localización y reglas operativas'])

@section('title', 'Configuración del sistema')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Configuración']]" />
@endsection

@section('content')
    <div class="space-y-6" x-data="{ activeTab: 'general' }">
        @if (session('status'))
            <x-flash type="success">{{ session('status') }}</x-flash>
        @endif

        @if ($errors->any())
            <x-flash type="danger">
                <ul class="list-disc pl-4 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-flash>
        @endif

        {{-- Sub-navegación rápida a catálogos de Fase 15 --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="{{ route('settings.pipelines.index') }}" class="block p-4 rounded-lg bg-white border border-slate-200 hover:border-slate-400 hover:shadow-sm transition-all">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Pipeline Comercial</span>
                    <span class="text-slate-400">→</span>
                </div>
                <p class="mt-1 font-semibold text-slate-900 text-sm">Pipelines y Etapas</p>
                <p class="text-xs text-slate-500 mt-0.5">Gestión de etapas, probabilidades y orden.</p>
            </a>
            <a href="{{ route('settings.product-categories.index') }}" class="block p-4 rounded-lg bg-white border border-slate-200 hover:border-slate-400 hover:shadow-sm transition-all">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Catálogo</span>
                    <span class="text-slate-400">→</span>
                </div>
                <p class="mt-1 font-semibold text-slate-900 text-sm">Categorías de Productos</p>
                <p class="text-xs text-slate-500 mt-0.5">Clasificación de productos comerciales.</p>
            </a>
            <a href="{{ route('settings.ticket-categories.index') }}" class="block p-4 rounded-lg bg-white border border-slate-200 hover:border-slate-400 hover:shadow-sm transition-all">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Soporte</span>
                    <span class="text-slate-400">→</span>
                </div>
                <p class="mt-1 font-semibold text-slate-900 text-sm">Categorías de Tickets</p>
                <p class="text-xs text-slate-500 mt-0.5">Tipificación de incidentes y solicitudes.</p>
            </a>
            <a href="{{ route('settings.quality') }}" class="block p-4 rounded-lg bg-white border border-slate-200 hover:border-slate-400 hover:shadow-sm transition-all">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Auditoría de Datos</span>
                    <span class="text-slate-400">→</span>
                </div>
                <p class="mt-1 font-semibold text-slate-900 text-sm">Calidad y Diagnósticos</p>
                <p class="text-xs text-slate-500 mt-0.5">Detección de incompletos y duplicados.</p>
            </a>
        </div>

        {{-- Formulario de configuración general --}}
        <x-card title="Parámetros del negocio" subtitle="Variables globales de identidad, localización y límites">
            <form action="{{ route('settings.update') }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                {{-- Pestañas de secciones --}}
                <div class="flex border-b border-slate-200 gap-4 text-xs font-medium">
                    <button type="button" @click="activeTab = 'general'"
                        :class="activeTab === 'general' ? 'border-b-2 border-slate-900 text-slate-900 font-semibold' : 'text-slate-500 hover:text-slate-700'"
                        class="pb-2.5 transition-colors">
                        Identidad y Localización
                    </button>
                    <button type="button" @click="activeTab = 'imports'"
                        :class="activeTab === 'imports' ? 'border-b-2 border-slate-900 text-slate-900 font-semibold' : 'text-slate-500 hover:text-slate-700'"
                        class="pb-2.5 transition-colors">
                        Límites de Importación
                    </button>
                </div>

                {{-- Pestaña Identidad y Localización --}}
                <div x-show="activeTab === 'general'" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <x-label for="company_name" value="Razón Social / Nombre Comercial *" />
                            <x-input id="company_name" name="company_name" type="text"
                                value="{{ old('company_name', $values['company_name'] ?? '') }}" required class="mt-1 w-full" />
                            <p class="mt-1 text-xs text-slate-500">Nombre público que aparece en cotizaciones, facturas y encabezados.</p>
                        </div>

                        <div>
                            <x-label for="company_country" value="País de Operación *" />
                            <x-input id="company_country" name="company_country" type="text"
                                value="{{ old('company_country', $values['company_country'] ?? '') }}" required class="mt-1 w-full" />
                            <p class="mt-1 text-xs text-slate-500">País sede de la organización.</p>
                        </div>

                        <div>
                            <x-label for="contact_email" value="Correo de Contacto *" />
                            <x-input id="contact_email" name="contact_email" type="email"
                                value="{{ old('contact_email', $values['contact_email'] ?? '') }}" required class="mt-1 w-full" />
                        </div>

                        <div>
                            <x-label for="contact_phone" value="Teléfono Institucional" />
                            <x-input id="contact_phone" name="contact_phone" type="text"
                                value="{{ old('contact_phone', $values['contact_phone'] ?? '') }}" class="mt-1 w-full" />
                        </div>

                        <div>
                            <x-label for="company_timezone" value="Zona Horaria Comercial (IANA) *" />
                            <select id="company_timezone" name="company_timezone" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
                                @foreach ($timezones as $tzKey => $tzLabel)
                                    <option value="{{ $tzKey }}" {{ old('company_timezone', $values['company_timezone'] ?? '') === $tzKey ? 'selected' : '' }}>
                                        {{ $tzLabel }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="mt-2 rounded bg-slate-50 p-2.5 text-xs text-slate-600 border border-slate-200">
                                <p><span class="font-medium text-slate-800">Hora comercial actual:</span> {{ $currentTime }}</p>
                                <p class="mt-0.5"><span class="font-medium text-slate-800">Rango 'Hoy' en UTC:</span> {{ $todayRange['start_utc']->format('H:i') }} a {{ $todayRange['end_utc']->format('H:i') }} UTC</p>
                            </div>
                        </div>

                        <div>
                            <x-label for="default_currency" value="Moneda por Defecto *" />
                            <select id="default_currency" name="default_currency" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
                                @foreach ($currencies as $currKey => $currLabel)
                                    <option value="{{ $currKey }}" {{ old('default_currency', $values['default_currency'] ?? '') === $currKey ? 'selected' : '' }}>
                                        {{ $currLabel }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Moneda asignada a nuevas oportunidades y cotizaciones. No modifica importes históricos existentes.</p>
                        </div>
                    </div>
                </div>

                {{-- Pestaña Límites de Importación --}}
                <div x-show="activeTab === 'imports'" x-cloak class="space-y-6" style="display: none;">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <x-label for="import_max_file_size_mb" value="Tamaño Máximo Archivo (MB) *" />
                            <x-input id="import_max_file_size_mb" name="import_max_file_size_mb" type="number" min="1" max="20"
                                value="{{ old('import_max_file_size_mb', $values['import_max_file_size_mb'] ?? 5) }}" required class="mt-1 w-full" />
                            <p class="mt-1 text-xs text-slate-500">Permite entre 1 MB y 20 MB por archivo CSV.</p>
                        </div>

                        <div>
                            <x-label for="import_max_rows" value="Límite Máximo de Filas *" />
                            <x-input id="import_max_rows" name="import_max_rows" type="number" min="100" max="10000"
                                value="{{ old('import_max_rows', $values['import_max_rows'] ?? 2000) }}" required class="mt-1 w-full" />
                            <p class="mt-1 text-xs text-slate-500">Procesamiento por streaming (hasta 10,000).</p>
                        </div>

                        <div>
                            <x-label for="import_token_ttl_minutes" value="Caducidad de Preview (minutos) *" />
                            <x-input id="import_token_ttl_minutes" name="import_token_ttl_minutes" type="number" min="5" max="120"
                                value="{{ old('import_token_ttl_minutes', $values['import_token_ttl_minutes'] ?? 30) }}" required class="mt-1 w-full" />
                            <p class="mt-1 text-xs text-slate-500">Tiempo antes de descartar el token de confirmación.</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-slate-200">
                    <button type="submit" class="rounded-md bg-slate-900 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 focus:outline-none">
                        Guardar configuración
                    </button>
                </div>
            </form>

            <div class="mt-8 pt-4 border-t border-slate-200 flex justify-between items-center text-xs text-slate-500">
                <p>Las credenciales de entorno (APP_KEY, base de datos, SMTP) no se gestionan en base de datos por seguridad.</p>
                <form action="{{ route('settings.reset') }}" method="POST" onsubmit="return confirm('¿Restaurar toda la configuración a los valores por defecto del sistema?');">
                    @csrf
                    <button type="submit" class="text-amber-800 hover:text-amber-900 font-semibold underline">
                        Restaurar valores predeterminados
                    </button>
                </form>
            </div>
        </x-card>
    </div>
@endsection
