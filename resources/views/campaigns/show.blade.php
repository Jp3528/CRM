@extends('layouts.app', ['header' => $campaign->name, 'subheader' => 'Ficha de campaña'])

@section('title', $campaign->name)

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Campañas', 'url' => route('campaigns.index')], ['label' => $campaign->name]]" />
@endsection

@section('content')
    <div class="flex flex-wrap items-center gap-2">
        <x-status-badge :status="$campaign->status" :label="ucfirst($campaign->status)" />
        <x-badge>{{ ucfirst($campaign->type) }}</x-badge>
        <span class="ml-auto flex gap-2 text-sm">
            @if ($canUpdate)<a href="{{ route('campaigns.edit', $campaign) }}" class="text-slate-700 hover:underline">Editar</a>@endif
            @if ($canManageMembers)<a href="{{ route('campaigns.audience', $campaign) }}" class="text-slate-700 hover:underline">Audiencia</a>@endif
            @can('delete', $campaign)
                <form method="POST" action="{{ route('campaigns.destroy', $campaign) }}" class="inline"
                    x-data @submit.prevent="if (confirm('¿Eliminar {{ $campaign->name }}?')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                </form>
            @endcan
        </span>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Datos generales">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">Nombre</dt><dd class="font-medium">{{ $campaign->name }}</dd></div>
                <div><dt class="text-slate-500">Responsable</dt><dd class="font-medium">{{ $campaign->owner?->name ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Inicio</dt><dd class="font-medium">{{ $campaign->start_at?->format('Y-m-d') ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Fin</dt><dd class="font-medium">{{ $campaign->end_at?->format('Y-m-d') ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Presupuesto</dt><dd class="font-medium">{{ $campaign->budget !== null ? number_format($campaign->budget, 2) : '—' }}</dd></div>
                <div><dt class="text-slate-500">Ingreso esperado</dt><dd class="font-medium">{{ $campaign->expected_revenue !== null ? number_format($campaign->expected_revenue, 2) : '—' }}</dd></div>
                <div><dt class="text-slate-500">Costo real</dt><dd class="font-medium">{{ $campaign->actual_cost !== null ? number_format($campaign->actual_cost, 2) : '—' }}</dd></div>
                <div><dt class="text-slate-500">Creada por</dt><dd class="font-medium">{{ $campaign->creator?->name ?? '—' }}</dd></div>
            </dl>
            @if ($campaign->description)
                <div class="mt-3 border-t border-slate-100 pt-3 text-sm"><p class="text-slate-500">Descripción</p><p class="mt-1 whitespace-pre-line">{{ $campaign->description }}</p></div>
            @endif
            <div class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-400">
                Creada {{ $campaign->created_at->format('Y-m-d H:i') }} · Actualizada {{ $campaign->updated_at->format('Y-m-d H:i') }}
            </div>
        </x-card>

        <x-card title="Resumen" subtitle="Solo registros en tu alcance">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">Miembros visibles</dt><dd class="text-lg font-semibold">{{ $scopedMembersCount }}</dd></div>
                <div><dt class="text-slate-500">Comunicaciones recientes</dt><dd class="text-lg font-semibold">{{ $communications->count() }}</dd></div>
            </dl>
            <p class="mt-2 text-xs text-slate-400">Sin métricas de delivery/open/click: no hay proveedor real en esta fase.</p>
        </x-card>
    </div>

    <x-card title="Miembros ({{ $scopedMembersCount }} visibles)" subtitle="Solo objetivos en tu alcance">
        @if ($canManageMembers)
            <form method="POST" action="{{ route('campaigns.members.store', $campaign) }}" class="mb-4 grid gap-2 md:grid-cols-4">
                @csrf
                <select name="member_type" required class="rounded-md border-slate-300 px-2 py-2 text-sm">
                    <option value="contact">Contacto</option>
                    <option value="lead">Lead</option>
                </select>
                <input type="number" name="member_id" required min="1" placeholder="ID visible en tu alcance"
                    class="rounded-md border-slate-300 px-3 py-2 text-sm">
                <input type="text" name="source" maxlength="100" placeholder="Origen (opcional)"
                    class="rounded-md border-slate-300 px-3 py-2 text-sm">
                <x-button>Agregar</x-button>
            </form>
            @if ($errors->has('member_id') || $errors->has('member_type'))
                <x-input-error :message="$errors->get('member_id')[0] ?? $errors->get('member_type')[0]" />
            @endif
        @endif

        @if ($members->isEmpty())
            <p class="text-sm text-slate-500">Sin miembros visibles. Agrega contactos o leads de tu alcance.</p>
        @else
            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Nombre</th>
                            <th class="px-4 py-2 text-left">Tipo</th>
                            <th class="px-4 py-2 text-left">Email/Teléfono</th>
                            <th class="px-4 py-2 text-left">Estado</th>
                            <th class="px-4 py-2 text-left">Origen</th>
                            <th class="px-4 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($members as $member)
                            @php $target = $member->getRelation('target_model'); @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2 font-medium">{{ $target ? trim(($target->first_name ?? '').' '.($target->last_name ?? '')) : '— (fuera de alcance)' }}</td>
                                <td class="px-4 py-2">{{ ucfirst($member->member_type) }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $target->email ?? $target->phone ?? '—' }}</td>
                                <td class="px-4 py-2"><x-status-badge :status="$member->status" :label="ucfirst($member->status)" /></td>
                                <td class="px-4 py-2 text-slate-500">{{ $member->source ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    @if ($canManageMembers)
                                        <form method="POST" action="{{ route('campaigns.members.unsubscribe', [$campaign, $member]) }}" class="inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="text-slate-600 hover:underline">Baja</button>
                                        </form>
                                        ·
                                        <form method="POST" action="{{ route('campaigns.members.destroy', [$campaign, $member]) }}" class="inline"
                                            x-data @submit.prevent="if (confirm('¿Quitar miembro?')) $el.submit()">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline">Quitar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $members->links() }}</div>
        @endif
    </x-card>

    <x-card title="Comunicaciones recientes" subtitle="Registro interno, sin envío externo">
        @if ($communications->isEmpty())
            <p class="text-sm text-slate-500">Sin comunicaciones simuladas todavía.</p>
        @else
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($communications as $comm)
                    <li class="flex items-center gap-2 py-2">
                        <a href="{{ route('communications.show', $comm) }}" class="font-medium hover:underline">{{ $comm->subject ?? '(sin asunto)' }}</a>
                        <x-badge>{{ ucfirst($comm->channel) }}</x-badge>
                        <x-status-badge :status="$comm->status" :label="ucfirst(str_replace('_', ' ', $comm->status))" />
                        <span class="ml-auto text-xs text-slate-400">{{ $comm->sent_at?->format('Y-m-d H:i') ?? $comm->created_at->format('Y-m-d H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($canCommunicate && $canManageMembers)
            <form method="POST" action="{{ route('campaigns.communications.bulk', $campaign) }}" class="mt-4 grid gap-2 border-t border-slate-100 pt-4 md:grid-cols-4">
                @csrf
                <select name="channel" required class="rounded-md border-slate-300 px-2 py-2 text-sm">
                    <option value="email">Email</option>
                    <option value="sms">SMS</option>
                    <option value="whatsapp">WhatsApp</option>
                </select>
                <input type="text" name="subject" maxlength="255" placeholder="Asunto (opcional)" class="rounded-md border-slate-300 px-3 py-2 text-sm">
                <input type="text" name="body" required maxlength="10000" placeholder="Mensaje (máx. 500 miembros)" class="rounded-md border-slate-300 px-3 py-2 text-sm md:col-span-2">
                <div class="md:col-span-4"><x-button>Registrar envío simulado masivo</x-button></div>
            </form>
            <p class="mt-1 text-xs text-slate-400">Máximo 500 miembros por operación. No se envía nada externo.</p>
        @endif
    </x-card>

    @if ($activities->isNotEmpty())
        <x-card title="Actividad reciente" subtitle="Eventos importantes de la campaña">
            <ul class="space-y-2 text-sm">
                @foreach ($activities as $activity)
                    <li class="flex gap-2"><span class="text-slate-500">{{ $activity->created_at->format('Y-m-d H:i') }}</span><span>{{ $activity->subject }}</span><span class="text-xs text-slate-400">· {{ $activity->user?->name }}</span></li>
                @endforeach
            </ul>
        </x-card>
    @endif
@endsection
