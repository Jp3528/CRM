@extends('layouts.app', ['header' => 'Empresas', 'subheader' => 'Primer módulo CRM funcional'])

@section('title', 'Empresas')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Empresas']]" />
@endsection

@section('content')
    <x-card title="Empresas" subtitle="{{ $companies->total() }} registro(s)">
        <form method="GET" action="{{ route('companies.index') }}" class="mb-4 grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-12">
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Buscar nombre, NIT, email, teléfono…"
                class="w-full min-w-0 rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500 sm:col-span-2 md:col-span-2 lg:col-span-4">
            <select name="status" class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm sm:col-span-1 md:col-span-1 lg:col-span-2">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $s)<option value="{{ $s }}" @selected($filters['status'] === $s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
            <select name="industry" class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm sm:col-span-1 md:col-span-1 lg:col-span-2">
                <option value="">Todas las industrias</option>
                @foreach ($industries as $i)<option value="{{ $i }}" @selected($filters['industry'] === $i)>{{ $i }}</option>@endforeach
            </select>
            <select name="country" class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm sm:col-span-1 md:col-span-1 lg:col-span-2">
                <option value="">Todos los países</option>
                @foreach ($countries as $c)<option value="{{ $c }}" @selected($filters['country'] === $c)>{{ $c }}</option>@endforeach
            </select>
            <select name="owner_id" class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm sm:col-span-1 md:col-span-1 lg:col-span-2">
                <option value="">Todos los responsables</option>
                @foreach ($owners as $o)<option value="{{ $o->id }}" @selected((string) $filters['owner_id'] === (string) $o->id)>{{ $o->name }}</option>@endforeach
            </select>
            <div class="col-span-full flex flex-wrap items-center gap-2 pt-1">
                <x-button>Buscar</x-button>
                <a href="{{ route('companies.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar</a>
                @if (auth()->user()?->hasPermission('exports.view'))
                    <a href="{{ route('exports.module', array_merge(['module' => 'companies'], request()->query())) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" title="Descargar registros filtrados en CSV compatible con Excel">
                        <svg class="mr-1.5 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Exportar CSV
                    </a>
                @endif
                @can('create', App\Models\Company::class)
                    <a href="{{ route('companies.create') }}" class="ml-auto inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Nueva empresa</a>
                @endcan
            </div>
        </form>

        @if ($companies->isEmpty())
            <x-empty-state title="No hay empresas registradas." message="Crea la primera empresa para empezar a gestionar tu CRM."
                :action-url="auth()->user()->can('create', App\Models\Company::class) ? route('companies.create') : null" action-label="Nueva empresa" />
        @else
            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('companies.index', array_merge(request()->query(), ['sort' => 'trade_name', 'direction' => $filters['sort'] === 'trade_name' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Nombre comercial{{ $filters['sort'] === 'trade_name' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">Industria</th>
                            <th class="px-4 py-2 text-left">Contacto</th>
                            <th class="px-4 py-2 text-left">Ciudad / País</th>
                            <th class="px-4 py-2 text-left">Responsable</th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('companies.index', array_merge(request()->query(), ['sort' => 'status', 'direction' => $filters['sort'] === 'status' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Estado{{ $filters['sort'] === 'status' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">Contactos</th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('companies.index', array_merge(request()->query(), ['sort' => 'created_at', 'direction' => $filters['sort'] === 'created_at' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Creada{{ $filters['sort'] === 'created_at' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($companies as $company)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2 font-medium text-slate-900">
                                    <a href="{{ route('companies.show', $company) }}" class="hover:underline">{{ $company->trade_name }}</a>
                                    @if ($company->legal_name)<span class="block text-xs font-normal text-slate-500">{{ $company->legal_name }}</span>@endif
                                </td>
                                <td class="px-4 py-2 text-slate-600">{{ $company->industry ?? '—' }}</td>
                                <td class="px-4 py-2 text-slate-600">
                                    @if ($company->email)<span class="block">{{ $company->email }}</span>@endif
                                    {{ $company->phone ?? ($company->email ? '' : '—') }}
                                </td>
                                <td class="px-4 py-2 text-slate-600">{{ trim(($company->city ?? '').($company->country ? ' / '.$company->country : ''), ' /') ?: '—' }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $company->owner?->name ?? '—' }}</td>
                                <td class="px-4 py-2"><x-status-badge :status="$company->status" /></td>
                                <td class="px-4 py-2 text-slate-600">{{ $company->contacts_count }}</td>
                                <td class="px-4 py-2 text-slate-500">{{ $company->created_at->format('Y-m-d') }}</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('companies.show', $company) }}" class="text-slate-600 hover:underline">Ver</a>
                                    @can('update', $company) · <a href="{{ route('companies.edit', $company) }}" class="text-slate-600 hover:underline">Editar</a>@endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $companies->links() }}</div>
        @endif
    </x-card>
@endsection
