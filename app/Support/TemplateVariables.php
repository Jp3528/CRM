<?php

namespace App\Support;

use App\Models\Contact;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Model;

/**
 * Variables permitidas en plantillas. Sustitución literal con strtr(),
 * sin Blade, sin eval(), sin ejecución de contenido.
 */
final class TemplateVariables
{
    public const VARIABLES = [
        'first_name',
        'last_name',
        'full_name',
        'company_name',
        'email',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public static function render(string $body, array $data = []): string
    {
        $map = [];
        foreach (self::VARIABLES as $var) {
            $map['{{'.$var.'}}'] = (string) ($data[$var] ?? '');
        }

        return strtr($body, $map);
    }

    public static function dataFor(?Model $model): array
    {
        if ($model instanceof Contact) {
            $model->loadMissing('company:id,trade_name');

            return [
                'first_name' => $model->first_name ?? '',
                'last_name' => $model->last_name ?? '',
                'full_name' => trim(($model->first_name ?? '').' '.($model->last_name ?? '')),
                'company_name' => $model->company?->trade_name ?? '',
                'email' => $model->email ?? '',
            ];
        }

        if ($model instanceof Lead) {
            return [
                'first_name' => $model->first_name ?? '',
                'last_name' => $model->last_name ?? '',
                'full_name' => trim(($model->first_name ?? '').' '.($model->last_name ?? '')),
                'company_name' => $model->company_name ?? '',
                'email' => $model->email ?? '',
            ];
        }

        return [
            'first_name' => 'Nombre',
            'last_name' => 'Ejemplo',
            'full_name' => 'Nombre Ejemplo',
            'company_name' => 'Empresa Ejemplo',
            'email' => 'ejemplo@example.com',
        ];
    }

    /** @return array<string, string> */
    public static function sample(): array
    {
        return [
            'first_name' => 'Nombre',
            'last_name' => 'Ejemplo',
            'full_name' => 'Nombre Ejemplo',
            'company_name' => 'Empresa Ejemplo',
            'email' => 'ejemplo@example.com',
        ];
    }
}
