<?php

namespace App\Http\Requests;

use App\Models\DataImport;
use App\Support\ImportCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadImportRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'max:5120', // 5 MB
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (! in_array($ext, ['csv', 'txt'], true)) {
                        $fail('El archivo debe tener formato CSV (.csv).');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'module.required' => 'Debes seleccionar un módulo para la importación.',
            'module.in' => 'El módulo seleccionado no es válido.',
            'file.required' => 'Debes adjuntar un archivo CSV.',
            'file.max' => 'El archivo no debe exceder los 5 MB.',
        ];
    }
}
