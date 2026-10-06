<?php

namespace App\Models;

use App\Support\DataScope;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory, SoftDeletes;

    public const TYPES = ['email', 'sms', 'whatsapp', 'phone', 'event', 'other'];

    public const STATUSES = ['draft', 'scheduled', 'active', 'paused', 'completed', 'cancelled'];

    /**
     * Transiciones permitidas (sin workflow engine).
     *
     * @var array<string, array<int, string>>
     */
    public const TRANSITIONS = [
        'draft' => ['scheduled', 'active', 'cancelled'],
        'scheduled' => ['active', 'cancelled'],
        'active' => ['paused', 'completed', 'cancelled'],
        'paused' => ['active', 'completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    /** Campañas editables (miembros y datos). */
    public const EDITABLE_STATUSES = ['draft', 'scheduled', 'active', 'paused'];

    public const SORTABLE = ['name', 'status', 'type', 'start_at', 'end_at', 'budget', 'created_at', 'updated_at'];

    protected $fillable = [
        'name', 'description', 'type', 'status', 'owner_id',
        'start_at', 'end_at', 'budget', 'expected_revenue', 'actual_cost',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'date',
            'end_at' => 'date',
            'budget' => 'decimal:2',
            'expected_revenue' => 'decimal:2',
            'actual_cost' => 'decimal:2',
        ];
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

    /** @return HasMany<CampaignMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(CampaignMember::class);
    }

    /** @return HasMany<Communication, $this> */
    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class)->latest();
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    public static function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** @param  Builder<Campaign>  $query */
    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        $term = mb_strtolower(trim($term));

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(campaigns.name) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(campaigns.description) LIKE ?', ["%{$term}%"]);
        });
    }

    /** @param  Builder<Campaign>  $query */
    public function scopeStatus($query, ?string $status)
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('campaigns.status', $status);
    }

    /** @param  Builder<Campaign>  $query */
    public function scopeType($query, ?string $type)
    {
        if (blank($type)) {
            return $query;
        }

        return $query->where('campaigns.type', $type);
    }

    /** @param  Builder<Campaign>  $query */
    public function scopeOwnedBy($query, mixed $ownerId)
    {
        if (blank($ownerId)) {
            return $query;
        }

        return $query->where('campaigns.owner_id', $ownerId);
    }

    /** @param  Builder<Campaign>  $query */
    public function scopeDateBetween($query, mixed $from, mixed $to)
    {
        if (! blank($from)) {
            $query->whereDate('campaigns.start_at', '>=', $from);
        }

        if (! blank($to)) {
            $query->whereDate('campaigns.end_at', '<=', $to);
        }

        return $query;
    }

    /** @param  Builder<Campaign>  $query */
    public function scopeVisibleTo($query, User $user)
    {
        return DataScope::scopeOwned($query, $user);
    }
}
