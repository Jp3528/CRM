<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Factura INTERNA (documento administrativo, NO fiscal). Sin pagos reales:
 * el estado paid es un registro manual interno.
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'sent', 'paid', 'overdue', 'cancelled'];

    /** paid es terminal salvo decisión futura con nota de crédito (fuera de fase). */
    public const SORTABLE = ['number', 'total', 'status', 'issue_date', 'due_date', 'created_at'];

    protected $fillable = [
        'number', 'sale_id', 'company_id', 'contact_id', 'owner_id',
        'status', 'currency', 'issue_date', 'due_date',
        'subtotal', 'discount_total', 'tax_total', 'total', 'notes',
        'company_name', 'company_tax_id', 'company_address',
        'contact_name', 'contact_email',
        'paid_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
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

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    /**
     * overdue efectivo (calculado, sin scheduler): vencida y pendiente de pago.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && ! in_array($this->status, ['paid', 'cancelled'], true);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Invoice>  $query */
    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        $term = mb_strtolower(trim($term));

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(invoices.number) LIKE ?', ["%{$term}%"])
                ->orWhereHas('sale', function ($sq) use ($term) {
                    $sq->whereRaw('LOWER(number) LIKE ?', ["%{$term}%"]);
                })
                ->orWhereHas('company', function ($cq) use ($term) {
                    $cq->whereRaw('LOWER(trade_name) LIKE ?', ["%{$term}%"]);
                })
                ->orWhereHas('contact', function ($cq) use ($term) {
                    $cq->whereRaw('LOWER(first_name) LIKE ?', ["%{$term}%"])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$term}%"]);
                });
        });
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Invoice>  $query */
    public function scopeStatus($query, ?string $status)
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('invoices.status', $status);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Invoice>  $query */
    public function scopeOwnedBy($query, mixed $ownerId)
    {
        if (blank($ownerId)) {
            return $query;
        }

        return $query->where('invoices.owner_id', $ownerId);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Invoice>  $query */
    public function scopeForCompany($query, mixed $companyId)
    {
        if (blank($companyId)) {
            return $query;
        }

        return $query->where('invoices.company_id', $companyId);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Invoice>  $query */
    public function scopeCurrency($query, ?string $currency)
    {
        if (blank($currency)) {
            return $query;
        }

        return $query->where('invoices.currency', $currency);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Invoice>  $query */
    public function scopeIssuedBetween($query, mixed $from, mixed $to)
    {
        if (! blank($from)) {
            $query->whereDate('invoices.issue_date', '>=', $from);
        }

        if (! blank($to)) {
            $query->whereDate('invoices.issue_date', '<=', $to);
        }

        return $query;
    }
}
