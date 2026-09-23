<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;

class ConvertLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead
            ? $this->user()?->can('convert', $lead) ?? false
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_mode' => ['required', 'string', 'in:new,existing'],
            'company_name' => ['required_if:company_mode,new', 'nullable', 'string', 'max:255'],
            'company_id' => ['required_if:company_mode,existing', 'nullable', 'integer', 'exists:companies,id'],
            'contact_mode' => ['required', 'string', 'in:new,existing'],
            'contact_id' => ['required_if:contact_mode,existing', 'nullable', 'integer', 'exists:contacts,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'create_opportunity' => ['sometimes', 'boolean'],
            'opportunity_name' => ['required_if:create_opportunity,1', 'nullable', 'string', 'max:255'],
            'opportunity_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'expected_close_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required_if' => 'Indica el nombre de la nueva empresa.',
            'company_id.required_if' => 'Selecciona una empresa existente.',
            'contact_id.required_if' => 'Selecciona un contacto existente.',
            'opportunity_name.required_if' => 'Indica el nombre de la oportunidad.',
            'opportunity_amount.min' => 'El monto no puede ser negativo.',
            'expected_close_date.after_or_equal' => 'La fecha de cierre no puede ser pasada.',
            'owner_id.exists' => 'El responsable seleccionado no existe.',
        ];
    }
}
