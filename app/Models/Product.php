<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    public const STATUSES = ['active', 'inactive'];

    public const UNITS = ['unit', 'hour', 'day', 'service', 'license', 'package'];

    public const SORTABLE = ['sku', 'name', 'price', 'status', 'created_at', 'updated_at'];

    protected $fillable = [
        'sku', 'name', 'description', 'category_id', 'unit',
        'price', 'cost', 'tax_rate', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'tax_rate' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<ProductCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<QuoteItem, $this> */
    public function quoteItems(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    /** @return HasMany<SaleItem, $this> */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /** @param  Builder<Product>  $query */
    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        $term = mb_strtolower(trim($term));

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(products.sku) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(products.name) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(products.description) LIKE ?', ["%{$term}%"])
                ->orWhereHas('category', function ($cq) use ($term) {
                    $cq->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"]);
                });
        });
    }

    /** @param  Builder<Product>  $query */
    public function scopeStatus($query, ?string $status)
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('products.status', $status);
    }

    /** @param  Builder<Product>  $query */
    public function scopeCategory($query, mixed $categoryId)
    {
        if (blank($categoryId)) {
            return $query;
        }

        return $query->where('products.category_id', $categoryId);
    }

    /** @param  Builder<Product>  $query */
    public function scopeUnit($query, ?string $unit)
    {
        if (blank($unit)) {
            return $query;
        }

        return $query->where('products.unit', $unit);
    }
}
