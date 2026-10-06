<?php

namespace App\Http\Requests;

use App\Models\Automation;
use App\Support\AutomationCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $automation = $this->route('automation');

        return $automation instanceof Automation
            ? $this->user()?->can('update', $automation) ?? false
            : false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // El estado solo cambia por activate/pause, nunca por input libre.
            'status' => ['prohibited'],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $automation = $this->route('automation');

            // Active no se edita directamente: pause → edit → activate.
            if ($automation instanceof Automation && $automation->status === 'active') {
                $validator->errors()->add('status', 'Pausa la automatización antes de editarla.');
            }
        });
    }
}
