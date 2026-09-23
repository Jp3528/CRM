@php
$s = $sale ?? null;
$quoteSourced = $s && $s->quote_id;
$initialItems = old('items', $s && ! $quoteSourced ? $s->items->map(fn ($i) => [
    'product_id' => $i->product_id, 'description' => $i->description, 'unit' => $i->unit,
    'quantity' => $i->quantity, 'unit_price' => $i->unit_price,
    'discount_type' => $i->discount_type, 'discount_value' => $i->discount_value,
    'tax_rate' => $i->tax_rate,
])->all() : [['product_id' => '', 'description' => '', 'unit' => 'unit', 'quantity' => 1, 'unit_price' => '', 'discount_type' => 'none', 'discount_value' => 0, 'tax_rate' => 0]]);
$initialHeader = [
    'company_id' => old('company_id', $s?->company_id ?? ''),
    'contact_id' => old('contact_id', $s?->contact_id ?? ''),
    'opportunity_id' => old('opportunity_id', $s?->opportunity_id ?? ''),
    'owner_id' => old('owner_id', $s?->owner_id ?? ''),
    'currency' => old('currency', $s?->currency ?? 'USD'),
];
@endphp

@if ($quoteSourced)
    <div class="md:col-span-2 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        Venta generada desde cotización: solo se pueden editar las notas para preservar la historia comercial.
    </div>
    <div class="md:col-span-2">
        <x-label for="notes" value="Notas" />
        <textarea id="notes" name="notes" rows="3"
            class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">{{ old('notes', $s->notes) }}</textarea>
    </div>
    @foreach (['company_id', 'contact_id', 'opportunity_id', 'owner_id', 'currency'] as $hidden)
        <input type="hidden" name="{{ $hidden }}" value="{{ $s->$hidden }}">
    @endforeach
    <input type="hidden" name="sale_date" value="{{ $s->sale_date?->format('Y-m-d') }}">
@else
    <div class="md:col-span-2 grid gap-4 md:grid-cols-3" x-data="quoteHeader(@json($initialHeader))" x-init="filterRelations()">
        <div>
            <x-label for="company_id" value="Empresa *" />
            <select id="company_id" name="company_id" required x-model="companyId" @change="filterRelations()"
                class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                <option value="">— Seleccionar —</option>
                @foreach ($companies as $c)<option value="{{ $c->id }}">{{ $c->trade_name }}</option>@endforeach
            </select>
            <x-input-error :message="$errors->get('company_id')[0] ?? null" />
        </div>
        <div>
            <x-label for="contact_id" value="Contacto" />
            <select id="contact_id" name="contact_id" x-model="contactId"
                class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                <option value="">— Sin contacto —</option>
                @foreach ($contacts as $c)<option value="{{ $c->id }}" data-company="{{ $c->company_id ?? '' }}">{{ $c->first_name }} {{ $c->last_name }}@if ($c->company) ({{ $c->company->trade_name }})@endif</option>@endforeach
            </select>
            <x-input-error :message="$errors->get('contact_id')[0] ?? null" />
        </div>
        <div>
            <x-label for="opportunity_id" value="Oportunidad" />
            <select id="opportunity_id" name="opportunity_id" x-model="opportunityId"
                class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                <option value="">— Ninguna —</option>
                @foreach ($opportunities as $o)<option value="{{ $o->id }}" data-company="{{ $o->company_id ?? '' }}">{{ $o->name }}@if ($o->company) ({{ $o->company->trade_name }})@endif</option>@endforeach
            </select>
            <x-input-error :message="$errors->get('opportunity_id')[0] ?? null" />
        </div>
        <div>
            <x-label for="owner_id" value="Responsable" />
            <select id="owner_id" name="owner_id"
                class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                <option value="">— Sin asignar —</option>
                @foreach ($owners as $o)<option value="{{ $o->id }}" @selected((string) $initialHeader['owner_id'] === (string) $o->id)>{{ $o->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <x-label for="currency" value="Moneda *" />
            <select id="currency" name="currency" required
                class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                @foreach ($currencies as $c)<option value="{{ $c }}" @selected($initialHeader['currency'] === $c)>{{ $c }}</option>@endforeach
            </select>
        </div>
        <div>
            <x-label for="sale_date" value="Fecha de venta" />
            <input id="sale_date" name="sale_date" type="date" value="{{ old('sale_date', $s?->sale_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        </div>
    </div>

    <div class="md:col-span-2" x-data="quoteLines(@json($initialItems), @json($productCatalog ?? []))">
        <div class="mb-2 flex items-center justify-between">
            <x-label value="Líneas (al menos una)" />
            <button type="button" @click="addLine()" class="text-sm text-slate-700 hover:underline">+ Agregar línea</button>
        </div>
        @if ($errors->has('items'))
            <x-input-error :message="$errors->get('items')[0]" />
        @endif
        <div class="space-y-3">
            <template x-for="(line, i) in lines" :key="i">
                <div class="rounded-md border border-slate-200 bg-slate-50/50 p-3">
                    <div class="grid gap-2 md:grid-cols-12">
                        <div class="md:col-span-4">
                            <label class="mb-1 block text-xs font-medium text-slate-600">Producto</label>
                            <select :name="`items[${i}][product_id]`" x-model="line.product_id" @change="prefill(i)"
                                class="block w-full rounded-md border-slate-300 px-2 py-1.5 text-sm">
                                <option value="">— Línea manual —</option>
                                @foreach ($products as $p)<option value="{{ $p->id }}">{{ $p->sku }} · {{ $p->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="md:col-span-4">
                            <label class="mb-1 block text-xs font-medium text-slate-600">Descripción *</label>
                            <input type="text" :name="`items[${i}][description]`" x-model="line.description"
                                class="block w-full rounded-md border-slate-300 px-2 py-1.5 text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-slate-600">Unidad</label>
                            <select :name="`items[${i}][unit]`" x-model="line.unit"
                                class="block w-full rounded-md border-slate-300 px-2 py-1.5 text-sm">
                                @foreach ($units as $u)<option value="{{ $u }}">{{ ucfirst($u) }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Cant. *</label>
                            <input type="number" step="0.001" min="0" :name="`items[${i}][quantity]`" x-model="line.quantity"
                                class="block w-full rounded-md border-slate-300 px-2 py-1.5 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">P. unit. *</label>
                            <input type="number" step="0.01" min="0" :name="`items[${i}][unit_price]`" x-model="line.unit_price"
                                class="block w-full rounded-md border-slate-300 px-2 py-1.5 text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-slate-600">Descuento</label>
                            <select :name="`items[${i}][discount_type]`" x-model="line.discount_type"
                                class="block w-full rounded-md border-slate-300 px-2 py-1.5 text-sm">
                                @foreach ($discountTypes as $d)<option value="{{ $d }}">{{ $d === 'none' ? 'Ninguno' : ($d === 'percentage' ? '% Porcentual' : 'Monto fijo') }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Valor desc.</label>
                            <input type="number" step="0.01" min="0" :name="`items[${i}][discount_value]`" x-model="line.discount_value"
                                class="block w-full rounded-md border-slate-300 px-2 py-1.5 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Imp. %</label>
                            <input type="number" step="0.01" min="0" max="100" :name="`items[${i}][tax_rate]`" x-model="line.tax_rate"
                                class="block w-full rounded-md border-slate-300 px-2 py-1.5 text-sm">
                        </div>
                        <div class="flex items-end justify-between md:col-span-2">
                            <span class="text-sm font-medium" x-text="'≈ ' + lineTotal(i)"></span>
                            <button type="button" @click="removeLine(i)" x-show="lines.length > 1" class="text-xs text-red-600 hover:underline">Quitar</button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
        <div class="mt-3 rounded-md bg-slate-50 px-4 py-3 text-sm">
            <p class="mb-1 text-xs text-slate-500">Vista previa — el backend recalcula y decide los valores finales.</p>
            <dl class="grid grid-cols-2 gap-1 sm:grid-cols-4">
                <div><dt class="text-slate-500">Subtotal</dt><dd class="font-medium" x-text="totals().subtotal"></dd></div>
                <div><dt class="text-slate-500">Descuento</dt><dd class="font-medium" x-text="totals().discount"></dd></div>
                <div><dt class="text-slate-500">Impuesto</dt><dd class="font-medium" x-text="totals().tax"></dd></div>
                <div><dt class="text-slate-500">Total</dt><dd class="font-semibold" x-text="totals().total"></dd></div>
            </dl>
        </div>
    </div>

    <div class="md:col-span-2">
        <x-label for="notes" value="Notas" />
        <textarea id="notes" name="notes" rows="2"
            class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">{{ old('notes', $s?->notes) }}</textarea>
    </div>

    <script>
    function quoteHeader(initial) {
        return {
            companyId: initial.company_id ? String(initial.company_id) : '',
            contactId: initial.contact_id ? String(initial.contact_id) : '',
            opportunityId: initial.opportunity_id ? String(initial.opportunity_id) : '',
            filterRelations() {
                document.querySelectorAll('#contact_id option[data-company]').forEach(opt => {
                    opt.hidden = this.companyId !== '' && opt.dataset.company !== '' && opt.dataset.company !== this.companyId;
                });
                document.querySelectorAll('#opportunity_id option[data-company]').forEach(opt => {
                    opt.hidden = this.companyId !== '' && opt.dataset.company !== '' && opt.dataset.company !== this.companyId;
                });
            },
        };
    }
    function quoteLines(initial, catalog) {
        const blank = () => ({ product_id: '', description: '', unit: 'unit', quantity: 1, unit_price: '', discount_type: 'none', discount_value: 0, tax_rate: 0 });
        return {
            lines: (initial && initial.length ? initial : [blank()]).map(l => ({
                product_id: l.product_id ? String(l.product_id) : '',
                description: l.description ?? '', unit: l.unit ?? 'unit',
                quantity: l.quantity ?? 1, unit_price: l.unit_price ?? '',
                discount_type: l.discount_type ?? 'none', discount_value: l.discount_value ?? 0,
                tax_rate: l.tax_rate ?? 0,
            })),
            addLine() { this.lines.push(blank()); },
            removeLine(i) { this.lines.splice(i, 1); },
            prefill(i) {
                const p = catalog[this.lines[i].product_id];
                if (!p) return;
                const l = this.lines[i];
                if (!l.description) l.description = p.name;
                if (l.unit_price === '' || l.unit_price === null) l.unit_price = p.price;
                if (!l.tax_rate) l.tax_rate = p.tax;
                l.unit = p.unit;
            },
            calc(l) {
                const qty = parseFloat(l.quantity) || 0;
                const price = parseFloat(l.unit_price) || 0;
                const tax = parseFloat(l.tax_rate) || 0;
                const base = qty * price;
                let disc = 0;
                if (l.discount_type === 'percentage') disc = base * (parseFloat(l.discount_value) || 0) / 100;
                if (l.discount_type === 'fixed') disc = Math.min(parseFloat(l.discount_value) || 0, base);
                const taxable = base - disc;
                const taxAmt = taxable * tax / 100;
                return { base, disc, tax: taxAmt, total: taxable + taxAmt };
            },
            lineTotal(i) { return this.calc(this.lines[i]).total.toFixed(2); },
            totals() {
                let s = 0, d = 0, t = 0;
                this.lines.forEach(l => { const c = this.calc(l); s += c.base; d += c.disc; t += c.tax; });
                const f = n => (Math.round(n * 100) / 100).toFixed(2);
                return { subtotal: f(s), discount: f(d), tax: f(t), total: f(s - d + t) };
            },
        };
    }
    </script>
@endif
