<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Contact::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', 'string', Rule::in(Contact::STATUSES)],
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
            'company_id.exists' => 'La empresa seleccionada no existe.',
            'owner_id.exists' => 'El responsable seleccionado no existe.',
            'status.in' => 'El estado seleccionado no es válido.',
        ];
    }
}
