<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Support\RelatedEntity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Task::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(['pending', 'in_progress'])],
            'priority' => ['required', 'string', Rule::in(Task::PRIORITIES)],
            'due_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            // Relación polimórfica con claves seguras (nunca clases desde el frontend).
            'related_type' => ['nullable', 'string', Rule::in(RelatedEntity::keys())],
            'related_id' => ['nullable', 'integer', 'required_with:related_type'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'El título es obligatorio.',
            'status.in' => 'El estado inicial debe ser pendiente o en progreso.',
            'priority.in' => 'La prioridad seleccionada no es válida.',
            'assigned_to.exists' => 'El usuario asignado no existe.',
            'related_type.in' => 'El tipo de entidad no es válido.',
        ];
    }
}
