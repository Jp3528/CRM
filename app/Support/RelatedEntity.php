<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Model;

/**
 * Whitelist centralizada de entidades relacionables polimórficamente
 * (tasks.taskable / activities.subjectable).
 *
 * El frontend solo envía claves seguras (company, contact, lead, opportunity);
 * nunca nombres de clase. Todo type/id se valida contra este mapa en backend.
 */
final class RelatedEntity
{
    public const MAP = [
        'company' => Company::class,
        'contact' => Contact::class,
        'lead' => Lead::class,
        'opportunity' => Opportunity::class,
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

    public static function keyForModel(?Model $model): ?string
    {
        return $model ? self::keyFor($model::class) : null;
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function findOrFail(string $key, mixed $id): Model
    {
        $class = self::classFor($key);

        abort_unless($class !== null, 422, 'Tipo de entidad no permitido.');

        return $class::findOrFail($id);
    }

    public static function label(?Model $model): string
    {
        if ($model instanceof Company) {
            return $model->trade_name;
        }

        if ($model instanceof Contact) {
            return trim("{$model->first_name} {$model->last_name}");
        }

        if ($model instanceof Lead) {
            return trim("{$model->first_name} {$model->last_name}");
        }

        if ($model instanceof Opportunity) {
            return $model->name;
        }

        return '—';
    }

    public static function showRoute(?Model $model): ?string
    {
        return match (true) {
            $model instanceof Company => 'companies.show',
            $model instanceof Contact => 'contacts.show',
            $model instanceof Lead => 'leads.show',
            $model instanceof Opportunity => 'opportunities.show',
            default => null,
        };
    }
}
