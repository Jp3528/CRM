@extends('layouts.app', ['header' => 'Productos', 'subheader' => 'Catálogo comercial'])

@section('title', 'Productos')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Productos']]" />
@endsection

@section('content')
    <x-card title="Productos" subtitle="{{ $products->total() }} registro(s)">
        <form method="GET" action="{{ route('products.index') }}" class="mb-4 grid gap-2 md:grid-cols-6">
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Buscar SKU, nombre, descripción…"
                class="rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500 md:col-span-2">
            <select name="status" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $s)<option value="{{ $s }}" @selected($filters['status'] === $s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
            <select name="category_id" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todas las categorías</option>
                @foreach ($categories as $c)<option value="{{ $c->id }}" @selected((string) $filters['category_id'] === (string) $c->id)>{{ $c->name }}</option>@endforeach
            </select>
            <select name="unit" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todas las unidades</option>
                @foreach ($units as $u)<option value="{{ $u }}" @selected($filters['unit'] === $u)>{{ ucfirst($u) }}</option>@endforeach
            </select>
            <div class="flex gap-2 md:col-span-6">
                <x-button>Buscar</x-button>
                <a href="{{ route('products.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar</a>
                @can('create', App\Models\Product::class)
                    <a href="{{ route('products.create') }}" class="ml-auto inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Nuevo producto</a>
                @endcan
            </div>
        </form>

        @if ($products->isEmpty())
            <x-empty-state title="No hay productos registrados." message="Crea el primer producto del catálogo."
                :action-url="auth()->user()->can('create', App\Models\Product::class) ? route('products.create') : null" action-label="Nuevo producto" />
        @else
            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('products.index', array_merge(request()->query(), ['sort' => 'sku', 'direction' => $filters['sort'] === 'sku' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">SKU{{ $filters['sort'] === 'sku' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('products.index', array_merge(request()->query(), ['sort' => 'name', 'direction' => $filters['sort'] === 'name' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Nombre{{ $filters['sort'] === 'name' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">Categoría</th>
                            <th class="px-4 py-2 text-left">Unidad</th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('products.index', array_merge(request()->query(), ['sort' => 'price', 'direction' => $filters['sort'] === 'price' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Precio{{ $filters['sort'] === 'price' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">Impuesto</th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('products.index', array_merge(request()->query(), ['sort' => 'status', 'direction' => $filters['sort'] === 'status' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Estado{{ $filters['sort'] === 'status' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($products as $product)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2 font-mono text-xs">{{ $product->sku }}</td>
                                <td class="px-4 py-2 font-medium text-slate-900">
                                    <a href="{{ route('products.show', $product) }}" class="hover:underline">{{ $product->name }}</a>
                                    <span class="block text-xs font-normal text-slate-400">usado en {{ $product->quote_items_count }} cotización(es)</span>
                                </td>
                                <td class="px-4 py-2 text-slate-600">{{ $product->category?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ ucfirst($product->unit) }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ number_format($product->price, 2) }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ number_format($product->tax_rate, 2) }}%</td>
                                <td class="px-4 py-2"><x-status-badge :status="$product->status" /></td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('products.show', $product) }}" class="text-slate-600 hover:underline">Ver</a>
                                    @can('update', $product) · <a href="{{ route('products.edit', $product) }}" class="text-slate-600 hover:underline">Editar</a>@endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $products->links() }}</div>
        @endif
    </x-card>
@endsection
