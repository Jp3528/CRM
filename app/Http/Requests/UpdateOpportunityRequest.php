<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\Opportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $opportunity = $this->route('opportunity');

        return $opportunity instanceof Opportunity
            ? $this->user()?->can('update', $opportunity) ?? false
            : false;
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
            'expected_close_date' => ['nullable', 'date'],
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
            'contact_id.exists' => 'El contacto seleccionado no existe.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $opportunity = $this->route('opportunity');
            $data = $this->validated();

            // El lead origen es trazabilidad: no se cambia una vez asignado.
            if ($opportunity instanceof Opportunity
                && $opportunity->lead_id !== null
                && (int) ($data['lead_id'] ?? 0) !== (int) $opportunity->lead_id
            ) {
                $validator->errors()->add('lead_id', 'El lead origen no puede cambiarse una vez asignado.');
            }

            // Coherencia empresa/contacto (sin vinculaciones silenciosas cruzadas).
            if (! empty($data['contact_id']) && ! empty($data['company_id'])) {
                $contact = Contact::find($data['contact_id']);
                if ($contact && $contact->company_id !== null && (int) $contact->company_id !== (int) $data['company_id']) {
                    $validator->errors()->add('contact_id', 'El contacto pertenece a otra empresa.');
                }
            }
        });
    }
}
