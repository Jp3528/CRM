@php
$a = $automation ?? new App\Models\Automation;
$initTrigger = old('trigger_type', $a->trigger_type ?? 'lead.created');
$initConditions = old('conditions', $a->conditions ?? []);
$initActions = old('actions', $a->actions ?? []);
$initOwner = old('owner_id', $a->owner_id);
@endphp

<div class="md:col-span-2">
    <x-label for="name" value="Nombre *" />
    <input id="name" name="name" type="text" required value="{{ old('name', $a->name) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
    <x-input-error :message="$errors->get('name')[0] ?? null" />
</div>
<div class="md:col-span-2">
    <x-label for="description" value="Descripcion" />
    <textarea id="description" name="description" rows="2"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">{{ old('description', $a->description) }}</textarea>
</div>
<div>
    <x-label for="owner_id" value="Responsable (autoridad de ejecucion) *" />
    <select id="owner_id" name="owner_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        <option value="">— Seleccionar —</option>
        @foreach ($owners as $u)<option value="{{ $u->id }}" @selected((string) $initOwner === (string) $u->id)>{{ $u->name }}</option>@endforeach
    </select>
    <x-input-error :message="$errors->get('owner_id')[0] ?? null" />
</div>

<div x-data="automationBuilder()" class="md:col-span-2 space-y-4">
    <div>
        <x-label for="trigger_type" value="Trigger *" />
        <select id="trigger_type" name="trigger_type" x-model="trigger" @change="onTriggerChange()"
            class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
            @foreach ($triggers as $t => $meta)<option value="{{ $t }}">{{ $meta['label'] }} ({{ $t }})</option>@endforeach
        </select>
        <x-input-error :message="$errors->get('trigger_type')[0] ?? null" />
    </div>

    <div class="rounded-md border border-slate-200 p-3">
        <p class="mb-2 text-sm font-medium">Condiciones (todas con AND, max. 10)</p>
        <template x-for="(c, i) in conditions" :key="'c'+i">
            <div class="mb-2 grid gap-2 md:grid-cols-4">
                <select :name="'conditions['+i+'][field]'" x-model="c.field" @change="c.operator = operatorsFor(c.field)[0]"
                    class="rounded-md border-slate-300 px-2 py-2 text-sm">
                    <template x-for="(ftype, fname) in fieldsForTrigger()" :key="fname">
                        <option :value="fname" x-text="fname"></option>
                    </template>
                </select>
                <select :name="'conditions['+i+'][operator]'" x-model="c.operator"
                    class="rounded-md border-slate-300 px-2 py-2 text-sm">
                    <template x-for="op in operatorsFor(c.field)" :key="op">
                        <option :value="op" x-text="op"></option>
                    </template>
                </select>
                <input type="text" :name="'conditions['+i+'][value]'" x-model="c.value"
                    placeholder="valor (listas: a, b)"
                    class="rounded-md border-slate-300 px-3 py-2 text-sm md:col-span-1">
                <button type="button" @click="conditions.splice(i, 1)"
                    class="rounded-md border border-slate-300 px-2 py-2 text-sm text-red-600 hover:bg-slate-50">Quitar</button>
            </div>
        </template>
        <button type="button" @click="addCondition()" x-show="conditions.length < 10"
            class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">+ Condicion</button>
        @if ($errors->has('conditions'))<x-input-error :message="$errors->get('conditions')[0]" />@endif
    </div>

    <div class="rounded-md border border-slate-200 p-3">
        <p class="mb-2 text-sm font-medium">Acciones (en orden, max. 5)</p>
        <template x-for="(a, i) in actions" :key="'a'+i">
            <div class="mb-3 rounded-md bg-slate-50 p-3">
                <div class="grid gap-2 md:grid-cols-3">
                    <select :name="'actions['+i+'][type]'" x-model="a.type"
                        class="rounded-md border-slate-300 px-2 py-2 text-sm">
                        @foreach (\App\Support\AutomationCatalog::ACTIONS as $t => $label)<option value="{{ $t }}">{{ $label }}</option>@endforeach
                    </select>
                    <button type="button" @click="actions.splice(i, 1)"
                        class="rounded-md border border-slate-300 bg-white px-2 py-2 text-sm text-red-600 hover:bg-slate-50 md:col-start-3">Quitar accion</button>
                </div>
                <div class="mt-2 grid gap-2 md:grid-cols-2" x-show="a.type === 'create_task'">
                    <input type="text" :name="'actions['+i+'][title]'" x-model="a.title" placeholder="Titulo * (variables: subject_name, subject_id, owner_name)"
                        class="rounded-md border-slate-300 px-3 py-2 text-sm md:col-span-2">
                    <input type="text" :name="'actions['+i+'][description]'" x-model="a.description" placeholder="Descripcion (opcional)"
                        class="rounded-md border-slate-300 px-3 py-2 text-sm md:col-span-2">
                    <select :name="'actions['+i+'][priority]'" x-model="a.priority" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                        <option value="low">low</option><option value="medium">medium</option>
                        <option value="high">high</option><option value="urgent">urgent</option>
                    </select>
                    <input type="number" :name="'actions['+i+'][due_in_days]'" x-model="a.due_in_days" min="0" max="365" placeholder="Dias vencimiento (0-365)"
                        class="rounded-md border-slate-300 px-3 py-2 text-sm">
                    <select :name="'actions['+i+'][assigned_to_mode]'" x-model="a.assigned_to_mode" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                        <option value="subject_owner">Responsable del registro</option>
                        <option value="automation_owner">Responsable automatizacion</option>
                        <option value="triggering_user">Usuario que disparo</option>
                        <option value="fixed_user">Usuario fijo</option>
                    </select>
                    <input type="number" :name="'actions['+i+'][fixed_user_id]'" x-model="a.fixed_user_id" min="1" placeholder="ID usuario fijo"
                        x-show="a.assigned_to_mode === 'fixed_user'" class="rounded-md border-slate-300 px-3 py-2 text-sm">
                </div>
                <div class="mt-2 grid gap-2 md:grid-cols-2" x-show="a.type === 'create_activity'">
                    <input type="text" :name="'actions['+i+'][subject]'" x-model="a.subject" placeholder="Asunto *"
                        class="rounded-md border-slate-300 px-3 py-2 text-sm md:col-span-2">
                    <input type="text" :name="'actions['+i+'][description]'" x-model="a.description" placeholder="Descripcion (opcional)"
                        class="rounded-md border-slate-300 px-3 py-2 text-sm md:col-span-2">
                    <select :name="'actions['+i+'][activity_type]'" x-model="a.activity_type" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                        <option value="note">note</option><option value="call">call</option><option value="email">email</option>
                    </select>
                    <select :name="'actions['+i+'][actor_mode]'" x-model="a.actor_mode" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                        <option value="automation_owner">Autor: responsable automatizacion</option>
                        <option value="triggering_user">Autor: usuario que disparo</option>
                    </select>
                </div>
                <div class="mt-2 grid gap-2 md:grid-cols-2" x-show="a.type === 'assign_owner'">
                    <select :name="'actions['+i+'][target_mode]'" x-model="a.target_mode" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                        <option value="automation_owner">Responsable automatizacion</option>
                        <option value="triggering_user">Usuario que disparo</option>
                        <option value="fixed_user">Usuario fijo</option>
                    </select>
                    <input type="number" :name="'actions['+i+'][fixed_user_id]'" x-model="a.fixed_user_id" min="1" placeholder="ID usuario fijo"
                        x-show="a.target_mode === 'fixed_user'" class="rounded-md border-slate-300 px-3 py-2 text-sm">
                </div>
                <div class="mt-2 grid gap-2" x-show="a.type === 'add_to_campaign'">
                    <input type="number" :name="'actions['+i+'][campaign_id]'" x-model="a.campaign_id" min="1" placeholder="ID campana visible *"
                        class="rounded-md border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
        </template>
        <button type="button" @click="addAction()" x-show="actions.length < 5"
            class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">+ Accion</button>
        @if ($errors->has('actions'))<x-input-error :message="$errors->get('actions')[0]" />@endif
    </div>
</div>

<script>
function automationBuilder() {
    const catalog = @js($catalog);
    return {
        trigger: @js($initTrigger),
        conditions: @js(array_values($initConditions ?? [])),
        actions: @js(array_values($initActions ?? [])),
        fieldsForTrigger() {
            return catalog.fields[this.trigger] ?? {};
        },
        operatorsFor(field) {
            const fields = this.fieldsForTrigger();
            const type = fields[field] ?? 'string';
            return catalog.operators[type] ?? [];
        },
        onTriggerChange() {
            this.conditions = [];
        },
        addCondition() {
            const fields = Object.keys(this.fieldsForTrigger());
            const field = fields[0] ?? 'status';
            this.conditions.push({field: field, operator: this.operatorsFor(field)[0] ?? 'equals', value: ''});
        },
        addAction() {
            this.actions.push({type: 'create_task', title: '', description: '', priority: 'medium', due_in_days: 3, assigned_to_mode: 'subject_owner', fixed_user_id: '', subject: '', activity_type: 'note', actor_mode: 'automation_owner', target_mode: 'automation_owner', campaign_id: ''});
        }
    };
}
</script>
