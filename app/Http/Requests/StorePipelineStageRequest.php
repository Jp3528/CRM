<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePipelineStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pipeline = $this->route('pipeline');

        return $this->user()?->can('update', $pipeline) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'probability' => ['required', 'integer', 'min:0', 'max:100'],
            'position' => ['nullable', 'integer', 'min:1'],
            'is_won' => ['nullable', 'boolean'],
            'is_lost' => ['nullable', 'boolean'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }
}
