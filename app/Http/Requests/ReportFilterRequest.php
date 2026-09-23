<?php

namespace App\Http\Requests;

use App\Services\Reports\ReportFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros de reportes: preset o fechas explícitas + responsable.
 * La resolución (alcance, límites) vive en ReportFilters.
 */
class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->hasPermission('reports.view') ?? false)
            || ($this->user()?->hasPermission('reports.forecast') ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'preset' => ['nullable', 'string', Rule::in([...ReportFilters::PRESETS, 'custom'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'owner_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => 'La fecha final debe ser posterior o igual a la inicial.',
        ];
    }
}
