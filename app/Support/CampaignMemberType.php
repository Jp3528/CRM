<?php

namespace App\Support;

use App\Models\Contact;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Model;

/**
 * Whitelist de miembros de campaña (contact|lead).
 *
 * El frontend solo envía claves seguras; nunca nombres de clase.
 */
final class CampaignMemberType
{
    public const MAP = [
        'contact' => Contact::class,
        'lead' => Lead::class,
    ];

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::MAP);
    }

    public static function classFor(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        return self::MAP[$key] ?? null;
    }

    public static function keyFor(?string $class): ?string
    {
        if ($class === null) {
            return null;
        }

        $key = array_search($class, self::MAP, true);

        return $key === false ? null : $key;
    }

    public static function resolve(string $key, mixed $id): ?Model
    {
        $class = self::classFor($key);

        if ($class === null) {
            return null;
        }

        return $class::find($id);
    }

    public static function label(?Model $model): string
    {
        if ($model instanceof Contact || $model instanceof Lead) {
            return trim("{$model->first_name} {$model->last_name}");
        }

        return '—';
    }
}
