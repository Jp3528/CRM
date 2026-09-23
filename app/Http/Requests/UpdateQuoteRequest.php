<?php

namespace App\Http\Requests;

use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quote = $this->route('quote');

        if (! $quote instanceof Quote) {
            return false;
        }

        // accepted/rejected son históricas: no se editan.
        if (! in_array($quote->status, Quote::EDITABLE_STATUSES, true)) {
            return false;
        }

        return $this->user()?->can('update', $quote) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:opportunities,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'currency' => ['required', 'string', Rule::in(Opportunity::CURRENCIES)],
            'issue_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.unit' => ['nullable', 'string', Rule::in(Product::UNITS)],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:9999999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'items.*.discount_type' => ['nullable', 'string', Rule::in(QuoteItem::DISCOUNT_TYPES)],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'Selecciona una empresa.',
            'items.required' => 'Agrega al menos una línea.',
            'items.min' => 'Agrega al menos una línea.',
            'items.*.quantity.min' => 'La cantidad debe ser mayor a 0.',
            'valid_until.after_or_equal' => 'La vigencia debe ser posterior a la emisión.',
        ];
    }
}
