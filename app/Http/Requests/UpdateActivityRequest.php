<?php

namespace App\Http\Requests;

use App\Models\Activity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $activity = $this->route('activity');

        if (! $activity instanceof Activity) {
            return false;
        }

        // Las actividades del sistema son trazabilidad: no se editan desde UI.
        if ($activity->is_system) {
            return false;
        }

        return $this->user()?->can('update', $activity) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(Activity::MANUAL_TYPES)],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(Activity::STATUSES)],
            'scheduled_at' => ['nullable', 'date', 'required_if:type,meeting'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'El tipo debe ser llamada, email, reunión o nota.',
            'subject.required' => 'El título es obligatorio.',
            'scheduled_at.required_if' => 'La reunión necesita fecha y hora programada.',
        ];
    }
}
