<?php

namespace App\Http\Requests;

use App\Models\Opportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Opportunity::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'currency' => ['required', 'string', Rule::in(Opportunity::CURRENCIES)],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'pipeline_id' => ['required', 'integer', 'exists:pipelines,id'],
            'pipeline_stage_id' => ['required', 'integer', 'exists:pipeline_stages,id'],
            'expected_close_date' => ['nullable', 'date'],
            'loss_reason' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
            'new_tags' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'amount.min' => 'El monto no puede ser negativo.',
            'company_id.required' => 'Selecciona una empresa.',
            'company_id.exists' => 'La empresa seleccionada no existe.',
            'contact_id.exists' => 'El contacto seleccionado no existe.',
            'pipeline_stage_id.exists' => 'La etapa seleccionada no existe.',
        ];
    }
}
