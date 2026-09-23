<?php

namespace App\Models;

use Database\Factories\TicketMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketMessage extends Model
{
    /** @use HasFactory<TicketMessageFactory> */
    use HasFactory, SoftDeletes;

    /** Tipos creables por usuarios (system solo lo crea el backend). */
    public const USER_TYPES = ['reply', 'note'];

    public const SYSTEM_TYPE = 'system';

    protected $fillable = [
        'ticket_id', 'user_id', 'contact_id', 'type', 'body', 'is_internal',
    ];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function getIsSystemAttribute(): bool
    {
        return $this->type === self::SYSTEM_TYPE;
    }
}
