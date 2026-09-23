<?php

namespace App\Models;

use App\Support\CampaignMemberType;
use Database\Factories\CampaignMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignMember extends Model
{
    /** @use HasFactory<CampaignMemberFactory> */
    use HasFactory;

    public const MEMBER_TYPES = ['contact', 'lead'];

    public const STATUSES = ['pending', 'sent', 'delivered', 'responded', 'converted', 'unsubscribed', 'failed'];

    /** Estados utilizables en esta fase (sin proveedor real, sin delivered falsificado por defecto). */
    public const ACTIVE_STATUSES = ['pending', 'sent', 'responded', 'converted', 'unsubscribed', 'failed'];

    protected $fillable = [
        'campaign_id', 'member_type', 'member_id', 'status',
        'source', 'added_by', 'responded_at', 'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<User, $this> */
    public function adder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /** @return HasMany<Communication, $this> */
    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class);
    }

    public function target(): ?Model
    {
        return CampaignMemberType::resolve($this->member_type, $this->member_id);
    }

    public function getTargetLabelAttribute(): string
    {
        return CampaignMemberType::label($this->target());
    }
}
