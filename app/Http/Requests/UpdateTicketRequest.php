<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return $ticket instanceof Ticket
            ? $this->user()?->can('update', $ticket) ?? false
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ticket = $this->route('ticket');
        $currentAssigneeId = $ticket instanceof Ticket ? $ticket->assigned_to : null;

        return [
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'requester_name' => ['nullable', 'string', 'max:255'],
            'requester_email' => ['nullable', 'email', 'max:255'],
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('status', 'active')
                        ->when($currentAssigneeId, fn ($q) => $q->orWhere('id', $currentAssigneeId))
                ),
            ],
            'category_id' => ['nullable', 'integer', 'exists:ticket_categories,id'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'string', Rule::in(Ticket::PRIORITIES)],
            'channel' => ['required', 'string', Rule::in(Ticket::CHANNELS)],
        ];
    }

    public function messages(): array
    {
        return [
            'subject.required' => 'El asunto es obligatorio.',
            'priority.in' => 'La prioridad seleccionada no es válida.',
            'contact_id.exists' => 'El contacto seleccionado no existe.',
            'assigned_to.exists' => 'El usuario asignado no existe o está inactivo.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $contactId = $this->input('contact_id');
            $companyId = $this->input('company_id');

            if (empty($contactId) && empty($this->input('requester_name')) && empty($this->input('requester_email'))) {
                $validator->errors()->add('requester_name', 'Indica un contacto o el nombre/email del solicitante.');
            }

            if (! empty($contactId) && ! empty($companyId)) {
                $contact = Contact::find($contactId);
                if ($contact && $contact->company_id !== null && (int) $contact->company_id !== (int) $companyId) {
                    $validator->errors()->add('contact_id', 'El contacto pertenece a otra empresa.');
                }
            }
        });
    }
}
