<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketMessageRequest extends FormRequest
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
        return [
            // system queda fuera: solo lo crea el backend.
            'type' => ['required', 'string', Rule::in(TicketMessage::USER_TYPES)],
            'body' => ['required', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'El tipo debe ser respuesta o nota interna.',
            'body.required' => 'El contenido es obligatorio.',
        ];
    }
}
