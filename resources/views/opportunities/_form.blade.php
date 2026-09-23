@php $o = $opportunity ?? new App\Models\Opportunity; $isEdit = isset($opportunity); @endphp

<div>
    <x-label for="name" value="Nombre *" />
    <input id="name" name="name" type="text" required value="{{ old('name', $o->name) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('name')[0] ?? null" />
</div>
<div>
    <x-label for="amount" value="Monto" />
    <input id="amount" name="amount" type="number" step="0.01" min="0" value="{{ old('amount', $o->amount) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('amount')[0] ?? null" />
</div>
<div>
    <x-label for="currency" value="Moneda *" />
    <select id="currency" name="currency" required class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        @foreach ($currencies as $c)<option value="{{ $c }}" @selected(old('currency', $o->currency ?? $defaultCurrency) === $c)>{{ $c }}</option>@endforeach
    </select>
</div>
<div>
    <x-label for="company_id" value="Empresa *" />
    <select id="company_id" name="company_id" required x-model="companyId" @change="filterContacts()"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        <option value="">— Seleccionar —</option>
        @foreach ($companies as $c)<option value="{{ $c->id }}" @selected((string) old('company_id', $o->company_id) === (string) $c->id)>{{ $c->trade_name }}</option>@endforeach
    </select>
    <x-input-error :message="$errors->get('company_id')[0] ?? null" />
</div>
<div>
    <x-label for="contact_id" value="Contacto" />
    <select id="contact_id" name="contact_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        <option value="">— Sin contacto —</option>
        @foreach ($contacts as $c)<option value="{{ $c->id }}" data-company="{{ $c->company_id ?? '' }}" @selected((string) old('contact_id', $o->contact_id) === (string) $c->id)>{{ $c->first_name }} {{ $c->last_name }}@if ($c->company) ({{ $c->company->trade_name }})@endif</option>@endforeach
    </select>
    <x-input-error :message="$errors->get('contact_id')[0] ?? null" />
</div>
@if (! $isEdit)
    <div>
        <x-label for="lead_id" value="Lead origen" />
        <select id="lead_id" name="lead_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
            <option value="">— Ninguno —</option>
            @foreach ($leads as $l)<option value="{{ $l->id }}" @selected((string) old('lead_id') === (string) $l->id)>{{ $l->first_name }} {{ $l->last_name }}@if ($l->company_name) ({{ $l->company_name }})@endif</option>@endforeach
        </select>
    </div>
    <div>
        <x-label for="pipeline_id" value="Pipeline *" />
        <select id="pipeline_id" name="pipeline_id" required x-model="pipelineId" @change="filterStages()"
            class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
            @foreach ($pipelines as $p)<option value="{{ $p->id }}" @selected((string) old('pipeline_id', $o->pipeline_id ?? $pipelines->firstWhere('is_default', true)?->id ?? $pipelines->first()?->id) === (string) $p->id)>{{ $p->name }}</option>@endforeach
        </select>
    </div>
    <div>
        <x-label for="pipeline_stage_id" value="Etapa inicial *" />
        <select id="pipeline_stage_id" name="pipeline_stage_id" required
            class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
            @foreach ($pipelines as $p)
                @foreach ($p->stages as $s)
                    <option value="{{ $s->id }}" data-pipeline="{{ $p->id }}" @selected((string) old('pipeline_stage_id', $o->pipeline_stage_id) === (string) $s->id)>{{ $p->name }} — {{ $s->name }} ({{ $s->probability }}%)</option>
                @endforeach
            @endforeach
        </select>
        <x-input-error :message="$errors->get('pipeline_stage_id')[0] ?? null" />
        <p class="mt-1 text-xs text-slate-500">La probabilidad y el estado se sincronizan con la etapa.</p>
    </div>
    <div>
        <x-label for="loss_reason" value="Motivo de pérdida (solo si inicia en Perdida)" />
        <input id="loss_reason" name="loss_reason" type="text" value="{{ old('loss_reason') }}"
            class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        <x-input-error :message="$errors->get('loss_reason')[0] ?? null" />
    </div>
@endif
<div>
    <x-label for="expected_close_date" value="Cierre previsto" />
    <input id="expected_close_date" name="expected_close_date" type="date" value="{{ old('expected_close_date', $o->expected_close_date?->format('Y-m-d')) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
</div>
<div>
    <x-label for="owner_id" value="Responsable" />
    <select id="owner_id" name="owner_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        <option value="">— Sin asignar —</option>
        @foreach ($owners as $own)<option value="{{ $own->id }}" @selected((string) old('owner_id', $o->owner_id) === (string) $own->id)>{{ $own->name }}</option>@endforeach
    </select>
    <x-input-error :message="$errors->get('owner_id')[0] ?? null" />
</div>
<div class="md:col-span-2">
    <x-label for="description" value="Descripción" />
    <textarea id="description" name="description" rows="3"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">{{ old('description', $o->description) }}</textarea>
</div>
<div class="md:col-span-2">
    <x-label value="Etiquetas" />
    <div class="flex flex-wrap gap-2">
        @foreach ($allTags as $tag)
            <label class="inline-flex items-center gap-1 rounded-full border border-slate-200 px-3 py-1 text-xs text-slate-700">
                <input type="checkbox" name="tags[]" value="{{ $tag->id }}"
                    @checked(in_array($tag->id, old('tags', $o->tags->pluck('id')->all() ?? []))) class="rounded border-slate-300">
                {{ $tag->name }}
            </label>
        @endforeach
        @if ($allTags->isEmpty())<span class="text-xs text-slate-400">Aún no hay etiquetas creadas.</span>@endif
    </div>
    <div class="mt-2">
        <x-label for="new_tags" value="Nuevas etiquetas (separadas por comas)" />
        <input id="new_tags" name="new_tags" type="text" value="{{ old('new_tags') }}" placeholder="enterprise, upsell…"
            class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    </div>
</div>

<script>
function oppForm() {
    return {
        companyId: document.getElementById('company_id')?.value || '',
        pipelineId: document.getElementById('pipeline_id')?.value || '',
        filterContacts() {
            this.companyId = document.getElementById('company_id').value;
            document.querySelectorAll('#contact_id option[data-company]').forEach(opt => {
                opt.hidden = this.companyId !== '' && opt.dataset.company !== '' && opt.dataset.company !== this.companyId;
            });
        },
        filterStages() {
            this.pipelineId = document.getElementById('pipeline_id').value;
            document.querySelectorAll('#pipeline_stage_id option[data-pipeline]').forEach(opt => {
                opt.hidden = opt.dataset.pipeline !== this.pipelineId;
            });
        },
    };
}
</script>
