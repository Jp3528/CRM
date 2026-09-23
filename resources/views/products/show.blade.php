@extends('layouts.app', ['header' => $product->name, 'subheader' => 'Ficha de producto'])

@section('title', $product->name)

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Productos', 'url' => route('products.index')], ['label' => $product->name]]" />
@endsection

@section('content')
    <div class="flex flex-wrap items-center gap-2">
        <x-status-badge :status="$product->status" />
        <span class="ml-auto flex gap-2 text-sm">
            @can('update', $product)<a href="{{ route('products.edit', $product) }}" class="text-slate-700 hover:underline">Editar</a>@endcan
            @can('delete', $product)
                <form method="POST" action="{{ route('products.destroy', $product) }}" class="inline"
                    x-data @submit.prevent="if (confirm('¿Eliminar {{ $product->sku }}? Las líneas históricas se conservan.')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                </form>
            @endcan
        </span>
    </div>

    <x-card title="Datos del producto">
        <dl class="grid grid-cols-2 gap-3 text-sm md:grid-cols-3">
            <div><dt class="text-slate-500">SKU</dt><dd class="font-mono font-medium">{{ $product->sku }}</dd></div>
            <div><dt class="text-slate-500">Nombre</dt><dd class="font-medium">{{ $product->name }}</dd></div>
            <div><dt class="text-slate-500">Categoría</dt><dd class="font-medium">{{ $product->category?->name ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Unidad</dt><dd class="font-medium">{{ ucfirst($product->unit) }}</dd></div>
            <div><dt class="text-slate-500">Precio</dt><dd class="font-medium">{{ number_format($product->price, 2) }}</dd></div>
            <div><dt class="text-slate-500">Impuesto</dt><dd class="font-medium">{{ number_format($product->tax_rate, 2) }}%</dd></div>
            @if ($canSeeCost)
                <div><dt class="text-slate-500">Costo (interno)</dt><dd class="font-medium">{{ $product->cost !== null ? number_format($product->cost, 2) : '—' }}</dd></div>
            @endif
            <div><dt class="text-slate-500">Creado por</dt><dd class="font-medium">{{ $product->creator?->name ?? '—' }}</dd></div>
        </dl>
        @if ($product->description)
            <div class="mt-3 border-t border-slate-100 pt-3 text-sm"><p class="text-slate-500">Descripción</p><p class="mt-1 whitespace-pre-line">{{ $product->description }}</p></div>
        @endif
        <div class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-400">
            Creado {{ $product->created_at->format('Y-m-d H:i') }} · Actualizado {{ $product->updated_at->format('Y-m-d H:i') }}
        </div>
    </x-card>

    <x-card title="Uso en cotizaciones ({{ $product->quote_items_count }})" subtitle="Histórico; cambiar el producto no lo altera">
        @if ($recentQuotes->isEmpty())
            <p class="text-sm text-slate-500">Sin uso registrado.</p>
        @else
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($recentQuotes as $item)
                    <li class="py-2">
                        @if ($item->quote)
                            <a href="{{ route('quotes.show', $item->quote) }}" class="font-medium hover:underline">{{ $item->quote->number }}</a>
                            <span class="text-xs text-slate-400">· {{ $item->quote->status }} · {{ number_format($item->total, 2) }}</span>
                        @else
                            <span class="text-slate-500">Cotización eliminada · {{ number_format($item->total, 2) }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
@endsection
