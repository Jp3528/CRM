@extends('layouts.app', ['header' => $lead->full_name, 'subheader' => 'Ficha de lead'])

@section('title', $lead->full_name)

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Leads', 'url' => route('leads.index')], ['label' => $lead->full_name]]" />
@endsection

@section('content')
    @if ($lead->isConverted())
        <div class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            Lead convertido el {{ $lead->converted_at?->format('Y-m-d H:i') }}.
            @if ($lead->convertedCompany && $canViewConvertedCompany)<a href="{{ route('companies.show', $lead->convertedCompany) }}" class="font-medium hover:underline">Ver empresa: {{ $lead->convertedCompany->trade_name }}</a>@endif
            @if ($lead->convertedContact && $canViewConvertedContact)<span class="mx-1">·</span><a href="{{ route('contacts.show', $lead->convertedContact) }}" class="font-medium hover:underline">Ver contacto: {{ $lead->convertedContact->full_name }}</a>@endif
            @if ($lead->opportunities->isNotEmpty())<span class="mx-1">·</span><span>{{ $lead->opportunities->count() }} oportunidad(es) vinculada(s).</span>@endif
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-2">
        <x-status-badge :status="$lead->status" />
        @if ($lead->source)<x-badge>{{ ucfirst($lead->source) }}</x-badge>@endif
        @foreach ($lead->tags as $tag)
            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-700 ring-1 ring-inset ring-slate-200">
                {{ $tag->name }}
                @if ($canUpdate && ! $lead->isConverted())
                    <form method="POST" action="{{ route('leads.tags.detach', [$lead, $tag]) }}" class="inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-slate-400 hover:text-red-600" title="Quitar">×</button>
                    </form>
                @endif
            </span>
        @endforeach
        <span class="ml-auto flex gap-2 text-sm">
            @if ($canUpdate && ! $lead->isConverted())
                <a href="{{ route('leads.edit', $lead) }}" class="text-slate-700 hover:underline">Editar</a>
                @if (in_array($lead->status, ['new', 'contacted'], true))
                    <form method="POST" action="{{ route('leads.qualify', $lead) }}" class="inline">
                        @csrf @method('PATCH')
                        <button type="submit" class="text-slate-700 hover:underline">Marcar calificado</button>
                    </form>
                @endif
            @endif
            @if ($canConvert)
                <a href="{{ route('leads.convert', $lead) }}" class="font-medium text-slate-900 hover:underline">Convertir →</a>
            @endif
            @can('create', App\Models\Task::class)<a href="{{ route('tasks.create', ['related' => 'lead:'.$lead->id]) }}" class="text-slate-700 hover:underline">Nueva tarea</a>@endcan
            @can('create', App\Models\Activity::class)<a href="{{ route('activities.create', ['related' => 'lead:'.$lead->id]) }}" class="text-slate-700 hover:underline">Registrar actividad</a>@endcan
            @can('create', App\Models\Communication::class)<a href="{{ route('communications.create', ['lead_id' => $lead->id]) }}" class="text-slate-700 hover:underline">Nueva comunicación</a>@endcan
            @can('delete', $lead)
                <form method="POST" action="{{ route('leads.destroy', $lead) }}" class="inline"
                    x-data @submit.prevent="if (confirm('¿Eliminar a {{ $lead->full_name }}?')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                </form>
            @endcan
        </span>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Datos del prospecto">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">Nombre</dt><dd class="font-medium">{{ $lead->full_name }}</dd></div>
                <div><dt class="text-slate-500">Empresa declarada</dt><dd class="font-medium">{{ $lead->company_name ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Email</dt><dd class="font-medium">{{ $lead->email ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Teléfono</dt><dd class="font-medium">{{ $lead->phone ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Origen</dt><dd class="font-medium">{{ $lead->source ? ucfirst($lead->source) : '—' }}</dd></div>
                <div><dt class="text-slate-500">Responsable</dt><dd class="font-medium">{{ $lead->owner?->name ?? '—' }}</dd></div>
            </dl>
        </x-card>

        <x-card title="Calificación y valor">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">Estado</dt><dd><x-status-badge :status="$lead->status" /></dd></div>
                <div>
                    <dt class="text-slate-500">Score ({{ $lead->score }}/100)</dt>
                    <dd class="mt-1 h-2 w-full overflow-hidden rounded-full bg-slate-200">
                        <span class="block h-full rounded-full {{ $lead->score >= 70 ? 'bg-green-500' : ($lead->score >= 40 ? 'bg-yellow-500' : 'bg-slate-400') }}" style="width: {{ $lead->score }}%"></span>
                    </dd>
                </div>
                <div><dt class="text-slate-500">Valor estimado</dt><dd class="font-medium">{{ $lead->estimated_value !== null ? number_format($lead->estimated_value, 2) : '—' }}</dd></div>
                <div><dt class="text-slate-500">Convertido</dt><dd class="font-medium">{{ $lead->converted_at ? $lead->converted_at->format('Y-m-d H:i') : 'No' }}</dd></div>
            </dl>
            @if ($lead->notes)
                <div class="mt-3 border-t border-slate-100 pt-3 text-sm"><p class="text-slate-500">Notas</p><p class="mt-1 whitespace-pre-line">{{ $lead->notes }}</p></div>
            @endif
            <div class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-400">
                Creado {{ $lead->created_at->format('Y-m-d H:i') }} · Actualizado {{ $lead->updated_at->format('Y-m-d H:i') }}
            </div>
        </x-card>
    </div>

    @if ($lead->opportunities->isNotEmpty())
        <x-card title="Oportunidades vinculadas" subtitle="Generadas desde este lead">
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($lead->opportunities as $opp)
                    <li class="py-2">
                        <p class="font-medium">{{ $opp->name }}</p>
                        <p class="text-xs text-slate-400">Monto {{ $opp->amount !== null ? number_format($opp->amount, 2).' '.$opp->currency : '—' }} · Etapa {{ $opp->stage?->name ?? '—' }} · {{ $opp->status }}</p>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @include('partials.timeline', ['subject' => $lead])

    @if (($canViewCampaigns ?? false) && ($recentCampaigns ?? collect())->isNotEmpty())
        <x-card title="Campañas recientes" subtitle="Audiencias del lead (en tu alcance)">
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($recentCampaigns as $campaign)
                    <li class="flex items-center gap-2 py-2">
                        <a href="{{ route('campaigns.show', $campaign) }}" class="font-medium hover:underline">{{ $campaign->name }}</a>
                        <x-status-badge :status="$campaign->status" :label="ucfirst($campaign->status)" />
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @if (($canViewCommunications ?? false) && ($recentCommunications ?? collect())->isNotEmpty())
        <x-card title="Comunicaciones recientes" subtitle="Registro interno, sin envío externo">
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($recentCommunications as $comm)
                    <li class="flex items-center gap-2 py-2">
                        <a href="{{ route('communications.show', $comm) }}" class="font-medium hover:underline">{{ $comm->subject ?? '(sin asunto)' }}</a>
                        <x-badge>{{ ucfirst($comm->channel) }}</x-badge>
                        <span class="ml-auto text-xs text-slate-400">{{ $comm->sent_at?->format('Y-m-d') ?? $comm->created_at->format('Y-m-d') }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
@endsection
