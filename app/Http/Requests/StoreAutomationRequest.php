<?php

namespace App\Http\Requests;

use App\Models\Automation;
use App\Support\AutomationCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Automation::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'trigger_type' => ['required', 'string', Rule::in(AutomationCatalog::triggers())],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
            'conditions' => ['nullable', 'array', 'max:'.AutomationCatalog::MAX_CONDITIONS],
            'conditions.*.field' => ['required', 'string', 'max:60'],
            'conditions.*.operator' => ['required', 'string', Rule::in(AutomationCatalog::OPERATORS)],
            'actions' => ['required', 'array', 'min:1', 'max:'.AutomationCatalog::MAX_ACTIONS],
            'actions.*.type' => ['required', 'string', Rule::in(array_keys(AutomationCatalog::ACTIONS))],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'trigger_type.in' => 'El trigger seleccionado no es válido.',
            'actions.required' => 'Indica al menos una acción.',
        ];
    }
}
