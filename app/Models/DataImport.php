<?php

namespace App\Models;

use App\Support\DataScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataImport extends Model
{
    public const STATUSES = ['processing', 'completed', 'completed_with_errors', 'failed'];

    protected $fillable = [
        'module', 'original_filename', 'status',
        'total_rows', 'successful_rows', 'failed_rows',
        'created_by', 'started_at', 'finished_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<DataImportError, $this> */
    public function errors(): HasMany
    {
        return $this->hasMany(DataImportError::class)->orderBy('row_number');
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<DataImport>  $query */
    public function scopeVisibleTo($query, User $user)
    {
        return DataScope::scopeCreatedBy($query, $user);
    }
}
