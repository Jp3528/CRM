<?php

namespace App\Http\Requests;

use App\Models\DataImport;
use App\Support\ImportCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $module = $this->input('module');

        return $this->user()->can('create', [DataImport::class, $module]);
    }

    public function rules(): array
    {
        return [
            'module' => ['required', 'string', Rule::in(ImportCatalog::MODULES)],
            'temp_path' => ['required', 'string'],
            'original_filename' => ['required', 'string', 'max:255'],
            'mapping' => ['required', 'array'],
        ];
    }
}
