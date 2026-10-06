<?php

namespace App\Models;

use App\Support\DataScope;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory, SoftDeletes;

    public const STATUSES = ['new', 'open', 'pending', 'resolved', 'closed'];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public const CHANNELS = ['web', 'email', 'phone', 'manual', 'other'];

    public const SORTABLE = ['number', 'priority', 'status', 'created_at', 'updated_at', 'last_reply_at'];

    protected $fillable = [
        'number', 'company_id', 'contact_id', 'requester_name', 'requester_email',
        'assigned_to', 'created_by', 'category_id', 'subject', 'description',
        'status', 'priority', 'channel',
        'first_response_at', 'resolved_at', 'closed_at', 'last_reply_at',
    ];

    protected function casts(): array
    {
        return [
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_reply_at' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<TicketCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class);
    }

    /** @return HasMany<TicketMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('created_at');
    }

    /**
     * Quién solicitó ayuda: contacto vinculado o datos libres del requester.
     */
    public function getRequesterLabelAttribute(): string
    {
        if ($this->contact) {
            return trim("{$this->contact->first_name} {$this->contact->last_name}");
        }

        return $this->requester_name ?: ($this->requester_email ?: '—');
    }

    /**
     * Antigüedad del caso (informativo, sin SLA contractual).
     */
    public function getAgeForHumansAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    public function getFirstResponseSecondsAttribute(): ?int
    {
        if (! $this->first_response_at) {
            return null;
        }

        return $this->created_at->diffInSeconds($this->first_response_at);
    }

    public function getResolutionSecondsAttribute(): ?int
    {
        if (! $this->resolved_at) {
            return null;
        }

        return $this->created_at->diffInSeconds($this->resolved_at);
    }

    /** @param  Builder<Ticket>  $query */
    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        $term = mb_strtolower(trim($term));

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(tickets.number) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(tickets.subject) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(tickets.description) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(tickets.requester_name) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(tickets.requester_email) LIKE ?', ["%{$term}%"])
                ->orWhereHas('company', function ($cq) use ($term) {
                    $cq->whereRaw('LOWER(trade_name) LIKE ?', ["%{$term}%"]);
                })
                ->orWhereHas('contact', function ($cq) use ($term) {
                    $cq->whereRaw('LOWER(first_name) LIKE ?', ["%{$term}%"])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$term}%"]);
                });
        });
    }

    /** @param  Builder<Ticket>  $query */
    public function scopeStatus($query, ?string $status)
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('tickets.status', $status);
    }

    /** @param  Builder<Ticket>  $query */
    public function scopePriority($query, ?string $priority)
    {
        if (blank($priority)) {
            return $query;
        }

        return $query->where('tickets.priority', $priority);
    }

    /** @param  Builder<Ticket>  $query */
    public function scopeCategory($query, mixed $categoryId)
    {
        if (blank($categoryId)) {
            return $query;
        }

        return $query->where('tickets.category_id', $categoryId);
    }

    /** @param  Builder<Ticket>  $query */
    public function scopeAssignedTo($query, mixed $userId)
    {
        if ($userId === 'unassigned') {
            return $query->whereNull('tickets.assigned_to');
        }

        if (blank($userId)) {
            return $query;
        }

        return $query->where('tickets.assigned_to', $userId);
    }

    /**
     * Alcance de datos (Fase 9.5): regla de soporte por rol.
     *
     * @param  Builder<Ticket>  $query
     */
    public function scopeVisibleTo($query, User $user)
    {
        return DataScope::scopeTickets($query, $user);
    }

    /** @param  Builder<Ticket>  $query */
    public function scopeForCompany($query, mixed $companyId)
    {
        if (blank($companyId)) {
            return $query;
        }

        return $query->where('tickets.company_id', $companyId);
    }

    /** @param  Builder<Ticket>  $query */
    public function scopeChannel($query, ?string $channel)
    {
        if (blank($channel)) {
            return $query;
        }

        return $query->where('tickets.channel', $channel);
    }
}
