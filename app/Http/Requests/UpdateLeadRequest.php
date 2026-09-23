<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        if (! $lead instanceof Lead) {
            return false;
        }

        // Los leads convertidos son históricos: no se editan para no romper trazabilidad.
        if ($lead->isConverted()) {
            return false;
        }

        return $this->user()?->can('update', $lead) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', Rule::in(Lead::SOURCES)],
            'status' => ['required', 'string', Rule::in(Lead::EDITABLE_STATUSES)],
            'score' => ['required', 'integer', 'min:0', 'max:100'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'notes' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
            'new_tags' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'El nombre es obligatorio.',
            'email.email' => 'Ingresa un correo válido.',
            'source.in' => 'El origen seleccionado no es válido.',
            'status.in' => 'El estado seleccionado no es válido.',
            'score.min' => 'El score debe estar entre 0 y 100.',
            'score.max' => 'El score debe estar entre 0 y 100.',
            'estimated_value.min' => 'El valor estimado no puede ser negativo.',
            'owner_id.exists' => 'El responsable seleccionado no existe.',
        ];
    }
}
