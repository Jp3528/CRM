<?php

namespace App\Http\Requests;

use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Campaign::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', 'string', Rule::in(Campaign::TYPES)],
            'status' => ['sometimes', 'string', Rule::in(Campaign::STATUSES)],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'expected_revenue' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'actual_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'type.in' => 'El tipo seleccionado no es válido.',
            'end_at.after_or_equal' => 'El fin debe ser posterior o igual al inicio.',
        ];
    }
}
