@php $p = $product ?? new App\Models\Product; @endphp

<div>
    <x-label for="sku" value="SKU *" />
    <input id="sku" name="sku" type="text" required value="{{ old('sku', $p->sku) }}" placeholder="CONSULT-01"
        class="block w-full rounded-md border-slate-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('sku')[0] ?? null" />
</div>
<div>
    <x-label for="name" value="Nombre *" />
    <input id="name" name="name" type="text" required value="{{ old('name', $p->name) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('name')[0] ?? null" />
</div>
<div>
    <x-label for="category_id" value="Categoría" />
    <select id="category_id" name="category_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        <option value="">— Sin categoría —</option>
        @foreach ($categories as $c)<option value="{{ $c->id }}" @selected((string) old('category_id', $p->category_id) === (string) $c->id)>{{ $c->name }}</option>@endforeach
    </select>
</div>
<div>
    <x-label for="unit" value="Unidad *" />
    <select id="unit" name="unit" required class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        @foreach ($units as $u)<option value="{{ $u }}" @selected(old('unit', $p->unit ?? 'unit') === $u)>{{ ucfirst($u) }}</option>@endforeach
    </select>
</div>
<div>
    <x-label for="price" value="Precio *" />
    <input id="price" name="price" type="number" step="0.01" min="0" required value="{{ old('price', $p->price) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('price')[0] ?? null" />
</div>
<div>
    <x-label for="cost" value="Costo (interno)" />
    <input id="cost" name="cost" type="number" step="0.01" min="0" value="{{ old('cost', $p->cost) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('cost')[0] ?? null" />
</div>
<div>
    <x-label for="tax_rate" value="Impuesto % *" />
    <input id="tax_rate" name="tax_rate" type="number" step="0.01" min="0" max="100" required value="{{ old('tax_rate', $p->tax_rate ?? '0.00') }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('tax_rate')[0] ?? null" />
</div>
<div>
    <x-label for="status" value="Estado *" />
    <select id="status" name="status" required class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        @foreach ($statuses as $s)<option value="{{ $s }}" @selected(old('status', $p->status ?? 'active') === $s)>{{ ucfirst($s) }}</option>@endforeach
    </select>
</div>
<div class="md:col-span-2">
    <x-label for="description" value="Descripción" />
    <textarea id="description" name="description" rows="3"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">{{ old('description', $p->description) }}</textarea>
</div>
