@extends('layouts.app', ['header' => 'Contactos', 'subheader' => 'Personas del CRM'])

@section('title', 'Contactos')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Contactos']]" />
@endsection

@section('content')
    <x-card title="Contactos" subtitle="{{ $contacts->total() }} registro(s)">
        <form method="GET" action="{{ route('contacts.index') }}" class="mb-4 grid gap-2 md:grid-cols-6">
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Buscar nombre, email, teléfono, cargo, empresa…"
                class="rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500 md:col-span-2">
            <select name="status" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $s)<option value="{{ $s }}" @selected($filters['status'] === $s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
            <select name="company_id" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todas las empresas</option>
                @foreach ($companies as $c)<option value="{{ $c->id }}" @selected((string) $filters['company_id'] === (string) $c->id)>{{ $c->trade_name }}</option>@endforeach
            </select>
            <select name="department" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los departamentos</option>
                @foreach ($departments as $d)<option value="{{ $d }}" @selected($filters['department'] === $d)>{{ $d }}</option>@endforeach
            </select>
            <select name="owner_id" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los responsables</option>
                @foreach ($owners as $o)<option value="{{ $o->id }}" @selected((string) $filters['owner_id'] === (string) $o->id)>{{ $o->name }}</option>@endforeach
            </select>
            <div class="flex gap-2 md:col-span-6">
                <x-button>Buscar</x-button>
                <a href="{{ route('contacts.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar</a>
                @if (auth()->user()?->hasPermission('exports.view'))
                    <a href="{{ route('exports.module', array_merge(['module' => 'contacts'], request()->query())) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" title="Descargar registros filtrados en CSV compatible con Excel">
                        <svg class="mr-1.5 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Exportar CSV
                    </a>
                @endif
                @can('create', App\Models\Contact::class)
                    <a href="{{ route('contacts.create') }}" class="ml-auto inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Nuevo contacto</a>
                @endcan
            </div>
        </form>

        @if ($contacts->isEmpty())
            <x-empty-state title="No hay contactos registrados." message="Crea el primer contacto para empezar."
                :action-url="auth()->user()->can('create', App\Models\Contact::class) ? route('contacts.create') : null" action-label="Nuevo contacto" />
        @else
            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('contacts.index', array_merge(request()->query(), ['sort' => 'first_name', 'direction' => $filters['sort'] === 'first_name' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Nombre{{ $filters['sort'] === 'first_name' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">Empresa</th>
                            <th class="px-4 py-2 text-left">Cargo / Depto.</th>
                            <th class="px-4 py-2 text-left">Contacto</th>
                            <th class="px-4 py-2 text-left">Responsable</th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('contacts.index', array_merge(request()->query(), ['sort' => 'status', 'direction' => $filters['sort'] === 'status' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Estado{{ $filters['sort'] === 'status' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('contacts.index', array_merge(request()->query(), ['sort' => 'created_at', 'direction' => $filters['sort'] === 'created_at' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Creado{{ $filters['sort'] === 'created_at' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($contacts as $contact)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2 font-medium text-slate-900">
                                    <a href="{{ route('contacts.show', $contact) }}" class="hover:underline">{{ $contact->full_name }}</a>
                                </td>
                                <td class="px-4 py-2 text-slate-600">
                                    @if ($contact->company)<a href="{{ route('companies.show', $contact->company) }}" class="hover:underline">{{ $contact->company->trade_name }}</a>@else — @endif
                                </td>
                                <td class="px-4 py-2 text-slate-600">{{ trim(($contact->job_title ?? '').($contact->department ? ' / '.$contact->department : ''), ' /') ?: '—' }}</td>
                                <td class="px-4 py-2 text-slate-600">
                                    @if ($contact->email)<span class="block">{{ $contact->email }}</span>@endif
                                    {{ $contact->phone ?? $contact->mobile ?? ($contact->email ? '' : '—') }}
                                </td>
                                <td class="px-4 py-2 text-slate-600">{{ $contact->owner?->name ?? '—' }}</td>
                                <td class="px-4 py-2"><x-status-badge :status="$contact->status" /></td>
                                <td class="px-4 py-2 text-slate-500">{{ $contact->created_at->format('Y-m-d') }}</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('contacts.show', $contact) }}" class="text-slate-600 hover:underline">Ver</a>
                                    @can('update', $contact) · <a href="{{ route('contacts.edit', $contact) }}" class="text-slate-600 hover:underline">Editar</a>@endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $contacts->links() }}</div>
        @endif
    </x-card>
@endsection
