<?php

namespace App\Http\Requests;

use App\Models\Opportunity;
use App\Models\Product;
use App\Models\QuoteItem;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sale = $this->route('sale');

        if (! $sale instanceof Sale) {
            return false;
        }

        if ($sale->status !== 'draft') {
            return false;
        }

        return $this->user()?->can('update', $sale) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sale = $this->route('sale');

        // Ventas con cotización origen: solo notas (historia comercial intacta).
        if ($sale instanceof Sale && $sale->quote_id !== null) {
            return ['notes' => ['nullable', 'string']];
        }

        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:opportunities,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'currency' => ['required', 'string', Rule::in(Opportunity::CURRENCIES)],
            'sale_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
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
            'items.*.quantity.min' => 'La cantidad debe ser mayor a 0.',
        ];
    }
}
