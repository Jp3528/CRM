<?php

namespace App\Http\Requests;

use App\Models\Pipeline;
use Illuminate\Foundation\Http\FormRequest;

class StorePipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Pipeline::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }
}
