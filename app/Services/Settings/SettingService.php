<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class SettingService
{
    public const CACHE_PREFIX = 'crm_setting_';

    public const CACHE_TTL = 3600;

    /** Catálogo cerrado de configuraciones del sistema */
    public const CATALOG = [
        'general' => [
            'company_name' => [
                'type' => 'string',
                'default' => 'NexusCRM S.A.S.',
                'label' => 'Razón Social / Nombre Comercial',
                'description' => 'Nombre público y comercial de la organización.',
                'rules' => ['required', 'string', 'max:255'],
                'is_public' => true,
            ],
            'company_country' => [
                'type' => 'string',
                'default' => 'Perú',
                'label' => 'País Principal',
                'description' => 'País de operaciones fiscales y comerciales.',
                'rules' => ['required', 'string', 'max:100'],
                'is_public' => true,
            ],
            'company_language' => [
                'type' => 'string',
                'default' => 'es',
                'label' => 'Idioma del Sistema',
                'description' => 'Idioma preferido de la plataforma.',
                'rules' => ['required', 'string', 'in:es,en'],
                'is_public' => true,
            ],
            'company_timezone' => [
                'type' => 'string',
                'default' => 'America/Lima',
                'label' => 'Zona Horaria Comercial (IANA)',
                'description' => 'Zona horaria para visualización de fechas y rangos comerciales.',
                'rules' => ['required', 'string', 'timezone:all'],
                'is_public' => true,
            ],
            'default_currency' => [
                'type' => 'string',
                'default' => 'USD',
                'label' => 'Moneda por Defecto',
                'description' => 'Moneda sugerida para nuevas cotizaciones y oportunidades.',
                'rules' => ['required', 'string', 'in:USD,EUR,PEN,COP'],
                'is_public' => true,
            ],
            'contact_email' => [
                'type' => 'string',
                'default' => 'contacto@nexuscrm.local',
                'label' => 'Correo de Contacto',
                'description' => 'Dirección de contacto institucional.',
                'rules' => ['required', 'email', 'max:255'],
                'is_public' => true,
            ],
            'contact_phone' => [
                'type' => 'string',
                'default' => '+51 1 234 5678',
                'label' => 'Teléfono Principal',
                'description' => 'Número de teléfono de la sede principal.',
                'rules' => ['nullable', 'string', 'max:50'],
                'is_public' => true,
            ],
        ],
        'imports' => [
            'import_max_file_size_mb' => [
                'type' => 'integer',
                'default' => 5,
                'label' => 'Tamaño Máximo de Archivo (MB)',
                'description' => 'Límite de tamaño para importación CSV.',
                'rules' => ['required', 'integer', 'min:1', 'max:20'],
                'is_public' => false,
            ],
            'import_max_rows' => [
                'type' => 'integer',
                'default' => 2000,
                'label' => 'Límite Máximo de Filas',
                'description' => 'Cantidad máxima de filas procesables por archivo CSV.',
                'rules' => ['required', 'integer', 'min:100', 'max:10000'],
                'is_public' => false,
            ],
            'import_token_ttl_minutes' => [
                'type' => 'integer',
                'default' => 30,
                'label' => 'Expiración de Previsualización (minutos)',
                'description' => 'Tiempo de validez del token de previsualización para confirmación.',
                'rules' => ['required', 'integer', 'min:5', 'max:120'],
                'is_public' => false,
            ],
        ],
    ];

    /** Prohíbe terminantemente almacenar credenciales o secretos en configuraciones */
    public static function isProhibitedKey(string $key): bool
    {
        $lower = strtolower($key);
        $forbidden = ['app_key', 'db_password', 'password', 'secret', 'api_key', 'private_key', 'auth_token', 'access_token', 'smtp_pass'];

        foreach ($forbidden as $bad) {
            if (str_contains($lower, $bad)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtiene el valor tipado de una configuración con fallback a su valor por defecto del catálogo.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = self::CACHE_PREFIX.$key;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default) {
            $setting = Setting::where('key', $key)->first();

            if ($setting && $setting->value !== null) {
                return self::castValue($setting->value, $setting->type);
            }

            // Buscar valor por defecto en catálogo
            foreach (self::CATALOG as $group) {
                if (isset($group[$key])) {
                    return $group[$key]['default'];
                }
            }

            return $default;
        });
    }

    /**
     * Guarda o actualiza una configuración respetando tipos y limpiando la caché.
     */
    public static function set(string $key, mixed $value): void
    {
        if (self::isProhibitedKey($key)) {
            throw ValidationException::withMessages([
                'key' => 'No está permitido almacenar claves o secretos de entorno en la base de datos.',
            ]);
        }

        $meta = self::findCatalogMeta($key);
        if (! $meta) {
            throw ValidationException::withMessages([
                'key' => "La clave de configuración '{$key}' no pertenece al catálogo permitido del sistema.",
            ]);
        }

        $group = $meta['group'];
        $type = $meta['type'];
        $isPublic = $meta['is_public'];
        $desc = $meta['description'];

        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => (string) $value,
                'type' => $type,
                'group' => $group,
                'is_public' => $isPublic,
                'description' => $desc,
            ]
        );

        Cache::forget(self::CACHE_PREFIX.$key);
    }

    /**
     * Invalida toda la caché de configuraciones.
     */
    public static function clearCache(): void
    {
        foreach (self::CATALOG as $group) {
            foreach ($group as $key => $meta) {
                Cache::forget(self::CACHE_PREFIX.$key);
            }
        }
    }

    /**
     * Retorna la zona horaria IANA configurada (validada).
     */
    public static function timezone(): string
    {
        $tz = (string) self::get('company_timezone', 'America/Lima');

        try {
            new DateTimeZone($tz);

            return $tz;
        } catch (\Throwable) {
            return 'America/Lima';
        }
    }

    /**
     * Retorna la hora actual en la zona horaria del sistema.
     */
    public static function now(): Carbon
    {
        return Carbon::now(self::timezone());
    }

    /**
     * Retorna el rango de inicio y fin del día "Hoy" en la zona horaria configurada,
     * convertido exactamente a UTC para consultas seguras en base de datos.
     *
     * @return array{start_utc: Carbon, end_utc: Carbon, local_date: string}
     */
    public static function todayRangeUtc(): array
    {
        $tz = self::timezone();
        $localNow = Carbon::now($tz);

        $startUtc = $localNow->copy()->startOfDay()->utc();
        $endUtc = $localNow->copy()->endOfDay()->utc();

        return [
            'start_utc' => $startUtc,
            'end_utc' => $endUtc,
            'local_date' => $localNow->format('Y-m-d'),
        ];
    }

    /**
     * Formatea un timestamp UTC a la zona horaria comercial.
     */
    public static function formatLocal(Carbon|\DateTimeInterface|string|null $date, string $format = 'd/m/Y H:i'): ?string
    {
        if (! $date) {
            return null;
        }

        $tz = self::timezone();
        $carbon = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);

        return $carbon->setTimezone($tz)->format($format);
    }

    private static function findCatalogMeta(string $key): ?array
    {
        foreach (self::CATALOG as $groupName => $group) {
            if (isset($group[$key])) {
                return array_merge($group[$key], ['group' => $groupName]);
            }
        }

        return null;
    }

    private static function castValue(string $val, string $type): mixed
    {
        return match ($type) {
            'integer', 'int' => (int) $val,
            'boolean', 'bool' => filter_var($val, FILTER_VALIDATE_BOOLEAN),
            'float' => (float) $val,
            default => $val,
        };
    }
}
