@extends('layouts.app', ['header' => 'Resultado de prueba', 'subheader' => $automation->name])

@section('title', 'Dry run')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Automatizaciones', 'url' => route('automations.index')], ['label' => $automation->name, 'url' => route('automations.show', $automation)], ['label' => 'Prueba']]" />
@endsection

@section('content')
    <x-card title="Dry run (sin cambios)" subtitle="Nada se creo ni modifico; tampoco se registro ejecucion">
        <dl class="grid gap-3 text-sm">
            <div><dt class="text-slate-500">Registro</dt><dd class="font-medium">{{ $subjectType }} #{{ $subjectId }}</dd></div>
            <div><dt class="text-slate-500">Trigger esperado</dt><dd class="font-medium">{{ $expectedSubject }}</dd></div>
            <div><dt class="text-slate-500">Compatible</dt><dd class="font-medium">{{ $compatible ? 'Si' : 'No' }}</dd></div>
            @if ($compatible)
                <div><dt class="text-slate-500">Condiciones</dt><dd class="font-medium">{{ $conditionsMet ? 'Se cumplen: ejecutaria.' : 'No se cumplen: se omitiria.' }}</dd></div>
                @if ($conditionsMet && $plan !== [])
                    <div><dt class="text-slate-500">Plan</dt>
                        <dd><ul class="list-disc pl-5">
                            @foreach ($plan as $step)<li><strong>{{ $step['action'] }}</strong>: {{ $step['detail'] }}</li>@endforeach
                        </ul></dd>
                    </div>
                @endif
            @endif
        </dl>
        <div class="mt-4"><a href="{{ route('automations.show', $automation) }}" class="text-sm text-slate-700 hover:underline">Volver a la automatizacion</a></div>
    </x-card>
@endsection
