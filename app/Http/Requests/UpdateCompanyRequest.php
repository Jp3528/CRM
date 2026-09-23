<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');

        return $company instanceof Company
            ? $this->user()?->can('update', $company) ?? false
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->route('company')?->id;

        return [
            'trade_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:50', Rule::unique('companies', 'tax_id')->ignore($companyId)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'industry' => ['nullable', 'string', 'max:100'],
            'company_size' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'string', Rule::in(Company::STATUSES)],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
            'new_tags' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'trade_name.required' => 'El nombre comercial es obligatorio.',
            'tax_id.unique' => 'Esa identificación fiscal ya está registrada.',
            'email.email' => 'Ingresa un correo válido.',
            'website.url' => 'Ingresa una URL válida (p. ej. https://empresa.com).',
            'status.in' => 'El estado seleccionado no es válido.',
            'owner_id.exists' => 'El responsable seleccionado no existe.',
        ];
    }
}
