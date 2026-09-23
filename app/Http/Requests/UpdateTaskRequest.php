<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Support\RelatedEntity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task
            ? $this->user()?->can('update', $task) ?? false
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(Task::STATUSES)],
            'priority' => ['required', 'string', Rule::in(Task::PRIORITIES)],
            'due_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'related_type' => ['nullable', 'string', Rule::in(RelatedEntity::keys())],
            'related_id' => ['nullable', 'integer', 'required_with:related_type'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'El título es obligatorio.',
            'status.in' => 'El estado seleccionado no es válido.',
            'priority.in' => 'La prioridad seleccionada no es válida.',
            'assigned_to.exists' => 'El usuario asignado no existe.',
            'related_type.in' => 'El tipo de entidad no es válido.',
        ];
    }
}
