<?php

namespace App\Models;

use App\Support\DataScope;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'confirmed', 'completed', 'cancelled'];

    public const EDITABLE_STATUSES = ['draft'];

    public const SORTABLE = ['number', 'total', 'status', 'sale_date', 'created_at'];

    protected $fillable = [
        'number', 'quote_id', 'company_id', 'contact_id', 'opportunity_id', 'owner_id',
        'status', 'currency', 'sale_date',
        'subtotal', 'discount_total', 'tax_total', 'total',
        'notes', 'completed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Opportunity, $this> */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<SaleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class)->orderBy('position');
    }

    /** @return HasOne<Invoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Sale>  $query */
    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        $term = mb_strtolower(trim($term));

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(sales.number) LIKE ?', ["%{$term}%"])
                ->orWhereHas('quote', function ($qq) use ($term) {
                    $qq->whereRaw('LOWER(number) LIKE ?', ["%{$term}%"]);
                })
                ->orWhereHas('company', function ($cq) use ($term) {
                    $cq->whereRaw('LOWER(trade_name) LIKE ?', ["%{$term}%"]);
                })
                ->orWhereHas('contact', function ($cq) use ($term) {
                    $cq->whereRaw('LOWER(first_name) LIKE ?', ["%{$term}%"])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$term}%"]);
                })
                ->orWhereHas('opportunity', function ($oq) use ($term) {
                    $oq->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"]);
                });
        });
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Sale>  $query */
    public function scopeStatus($query, ?string $status)
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('sales.status', $status);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Sale>  $query */
    public function scopeOwnedBy($query, mixed $ownerId)
    {
        if (blank($ownerId)) {
            return $query;
        }

        return $query->where('sales.owner_id', $ownerId);
    }

    /**
     * Alcance de datos (Fase 9.5): filtra por propietario/equipo según rol.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Sale>  $query
     */
    public function scopeVisibleTo($query, User $user)
    {
        return DataScope::scopeOwned($query, $user);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Sale>  $query */
    public function scopeForCompany($query, mixed $companyId)
    {
        if (blank($companyId)) {
            return $query;
        }

        return $query->where('sales.company_id', $companyId);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Sale>  $query */
    public function scopeCurrency($query, ?string $currency)
    {
        if (blank($currency)) {
            return $query;
        }

        return $query->where('sales.currency', $currency);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Sale>  $query */
    public function scopeSoldBetween($query, mixed $from, mixed $to)
    {
        if (! blank($from)) {
            $query->whereDate('sales.sale_date', '>=', $from);
        }

        if (! blank($to)) {
            $query->whereDate('sales.sale_date', '<=', $to);
        }

        return $query;
    }
}
