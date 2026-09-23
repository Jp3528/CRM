<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? $this->user()?->can('update', $product) ?? false
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($productId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'unit' => ['required', 'string', Rule::in(Product::UNITS)],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', 'string', Rule::in(Product::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.required' => 'El SKU es obligatorio.',
            'sku.unique' => 'Ese SKU ya está registrado.',
            'name.required' => 'El nombre es obligatorio.',
            'price.min' => 'El precio no puede ser negativo.',
            'tax_rate.max' => 'El impuesto debe estar entre 0 y 100.',
            'status.in' => 'El estado seleccionado no es válido.',
        ];
    }
}
