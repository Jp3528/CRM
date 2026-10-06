<?php

namespace App\Models;

use App\Support\DataScope;
use App\Support\TemplateVariables;
use Database\Factories\MessageTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessageTemplate extends Model
{
    /** @use HasFactory<MessageTemplateFactory> */
    use HasFactory, SoftDeletes;

    public const CHANNELS = ['email', 'sms', 'whatsapp'];

    public const STATUSES = ['draft', 'active', 'archived'];

    public const SORTABLE = ['name', 'channel', 'status', 'created_at', 'updated_at'];

    protected $fillable = [
        'name', 'channel', 'subject', 'body', 'status', 'owner_id', 'created_by',
    ];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function preview(array $data = []): string
    {
        return TemplateVariables::render($this->body, $data ?: TemplateVariables::sample());
    }

    /** @param  Builder<MessageTemplate>  $query */
    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        $term = mb_strtolower(trim($term));

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(message_templates.name) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(message_templates.subject) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(message_templates.body) LIKE ?', ["%{$term}%"]);
        });
    }

    /** @param  Builder<MessageTemplate>  $query */
    public function scopeChannel($query, ?string $channel)
    {
        if (blank($channel)) {
            return $query;
        }

        return $query->where('message_templates.channel', $channel);
    }

    /** @param  Builder<MessageTemplate>  $query */
    public function scopeStatus($query, ?string $status)
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('message_templates.status', $status);
    }

    /** @param  Builder<MessageTemplate>  $query */
    public function scopeOwnedBy($query, mixed $ownerId)
    {
        if (blank($ownerId)) {
            return $query;
        }

        return $query->where('message_templates.owner_id', $ownerId);
    }

    /** @param  Builder<MessageTemplate>  $query */
    public function scopeVisibleTo($query, User $user)
    {
        return DataScope::scopeOwned($query, $user);
    }
}
