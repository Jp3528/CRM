<?php

namespace App\Http\Requests;

use App\Support\DataScope;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('imports.create')
            && ! DataScope::isReadOnly($this->user());
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:100'],
        ];
    }
}
