<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'category_id' => ['nullable', 'integer'],
            'unit' => ['nullable', 'string', 'max:30'],
            'sort' => ['nullable', 'string', 'max:30'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $sort = in_array($validated['sort'] ?? '', Product::SORTABLE, true)
            ? $validated['sort']
            : 'created_at';
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $products = Product::query()
            ->with('category:id,name')
            ->withCount('quoteItems')
            ->search($validated['search'] ?? null)
            ->status($validated['status'] ?? null)
            ->category($validated['category_id'] ?? null)
            ->unit($validated['unit'] ?? null)
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
                'category_id' => $validated['category_id'] ?? '',
                'unit' => $validated['unit'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statuses' => Product::STATUSES,
            'units' => Product::UNITS,
            'categories' => ProductCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('products.create', $this->formData());
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $product = Product::create(array_merge($data, [
            'sku' => strtoupper(trim($data['sku'])),
            'created_by' => $request->user()->id,
        ]));

        return redirect()->route('products.show', $product)
            ->with('success', 'Producto creado correctamente.');
    }

    public function show(Product $product): View
    {
        $this->authorize('view', $product);

        $product->load(['category:id,name', 'creator:id,name'])
            ->loadCount('quoteItems');

        $recentQuotes = $product->quoteItems()
            ->with('quote:id,number,status,total,currency')
            ->latest()
            ->limit(5)
            ->get();

        return view('products.show', [
            'product' => $product,
            // Costo interno: visible solo para quien puede editar productos.
            'canSeeCost' => request()->user()->can('update', $product),
            'recentQuotes' => $recentQuotes,
        ]);
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        return view('products.edit', array_merge(
            $this->formData(),
            ['product' => $product]
        ));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();

        $product->update(array_merge($data, [
            'sku' => strtoupper(trim($data['sku'])),
        ]));

        return redirect()->route('products.show', $product)
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        // Soft delete: los QuoteItems históricos se conservan (product_id nullOnDelete).
        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Producto eliminado correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'categories' => ProductCategory::orderBy('name')->get(['id', 'name']),
            'units' => Product::UNITS,
            'statuses' => Product::STATUSES,
        ];
    }
}
