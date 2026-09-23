<?php

namespace App\Models;

use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

    /** Estados editables comercialmente (accepted/rejected protegidas). */
    public const EDITABLE_STATUSES = ['draft', 'sent'];

    public const SORTABLE = ['number', 'total', 'status', 'issue_date', 'valid_until', 'created_at'];

    protected $fillable = [
        'number', 'company_id', 'contact_id', 'opportunity_id', 'owner_id',
        'status', 'currency', 'issue_date', 'valid_until',
        'subtotal', 'discount_total', 'tax_total', 'total',
        'notes', 'terms', 'accepted_at', 'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'valid_until' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
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

    /** @return HasMany<QuoteItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('position');
    }

    /**
     * Vencida por fecha sin resolución. Calculado (sin scheduler en esta fase):
     * valid_until pasada y estado pendiente de decisión.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->valid_until !== null
            && $this->valid_until->isPast()
            && ! in_array($this->status, ['accepted', 'rejected'], true);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Quote>  $query */
    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        $term = mb_strtolower(trim($term));

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(quotes.number) LIKE ?', ["%{$term}%"])
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

    /** @param  \Illuminate\Database\Eloquent\Builder<Quote>  $query */
    public function scopeStatus($query, ?string $status)
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('quotes.status', $status);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Quote>  $query */
    public function scopeOwnedBy($query, mixed $ownerId)
    {
        if (blank($ownerId)) {
            return $query;
        }

        return $query->where('quotes.owner_id', $ownerId);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Quote>  $query */
    public function scopeForCompany($query, mixed $companyId)
    {
        if (blank($companyId)) {
            return $query;
        }

        return $query->where('quotes.company_id', $companyId);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Quote>  $query */
    public function scopeCurrency($query, ?string $currency)
    {
        if (blank($currency)) {
            return $query;
        }

        return $query->where('quotes.currency', $currency);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Quote>  $query */
    public function scopeIssuedBetween($query, mixed $from, mixed $to)
    {
        if (! blank($from)) {
            $query->whereDate('quotes.issue_date', '>=', $from);
        }

        if (! blank($to)) {
            $query->whereDate('quotes.issue_date', '<=', $to);
        }

        return $query;
    }
}
