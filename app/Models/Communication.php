<?php

namespace App\Models;

use App\Support\DataScope;
use Database\Factories\CommunicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Communication extends Model
{
    /** @use HasFactory<CommunicationFactory> */
    use HasFactory, SoftDeletes;

    public const CHANNELS = ['email', 'sms', 'whatsapp'];

    public const DIRECTIONS = ['outbound', 'inbound'];

    /**
     * Estados de esta fase: todo envío es interno/simulado.
     * delivered/opened/clicked quedan fuera del flujo actual (reservados a futuro proveedor).
     */
    public const STATUSES = ['draft', 'queued', 'simulated_sent', 'failed', 'cancelled'];

    public const EDITABLE_STATUSES = ['draft', 'queued'];

    public const SORTABLE = ['channel', 'status', 'sent_at', 'created_at', 'updated_at'];

    protected $fillable = [
        'campaign_id', 'campaign_member_id', 'contact_id', 'lead_id',
        'template_id', 'channel', 'direction', 'subject', 'body', 'status',
        'owner_id', 'created_by', 'sent_at', 'failed_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<CampaignMember, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(CampaignMember::class, 'campaign_member_id');
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<MessageTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

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

    public function isSimulated(): bool
    {
        return $this->status === 'simulated_sent';
    }

    public function target(): Contact|Lead|null
    {
        return $this->contact ?? $this->lead;
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Communication>  $query */
    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        $term = mb_strtolower(trim($term));

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(communications.subject) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(communications.body) LIKE ?', ["%{$term}%"]);
        });
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Communication>  $query */
    public function scopeStatus($query, ?string $status)
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('communications.status', $status);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Communication>  $query */
    public function scopeChannel($query, ?string $channel)
    {
        if (blank($channel)) {
            return $query;
        }

        return $query->where('communications.channel', $channel);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Communication>  $query */
    public function scopeForCampaign($query, mixed $campaignId)
    {
        if (blank($campaignId)) {
            return $query;
        }

        return $query->where('communications.campaign_id', $campaignId);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Communication>  $query */
    public function scopeVisibleTo($query, User $user)
    {
        return DataScope::scopeCommunications($query, $user);
    }
}
