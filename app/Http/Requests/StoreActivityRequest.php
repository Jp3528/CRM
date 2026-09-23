<?php

namespace App\Http\Requests;

use App\Models\Activity;
use App\Support\RelatedEntity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Activity::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Solo tipos manuales: status_change y demás sistema quedan fuera.
            'type' => ['required', 'string', Rule::in(Activity::MANUAL_TYPES)],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(Activity::STATUSES)],
            'scheduled_at' => ['nullable', 'date', 'required_if:type,meeting'],
            'related_type' => ['nullable', 'string', Rule::in(RelatedEntity::keys())],
            'related_id' => ['nullable', 'integer', 'required_with:related_type'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'El tipo debe ser llamada, email, reunión o nota.',
            'subject.required' => 'El título es obligatorio.',
            'status.in' => 'El estado seleccionado no es válido.',
            'scheduled_at.required_if' => 'La reunión necesita fecha y hora programada.',
            'related_type.in' => 'El tipo de entidad no es válido.',
        ];
    }
}
