<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    /** Claves sensibles que nunca deben persistirse en los logs de auditoría. */
    protected const PROHIBITED_KEYS = [
        'password',
        'password_confirmation',
        'remember_token',
        'secret',
        'app_key',
        'token',
        'auth_token',
        'access_token',
        'api_key',
        'db_password',
        'mail_password',
    ];

    /**
     * Registra una acción de auditoría sobre un modelo Eloquent.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        ?User $actor,
        Model $entity,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        return $this->logAction(
            actor: $actor,
            action: $action,
            entityType: $entity->getMorphClass(),
            entityId: $entity->getKey(),
            oldValues: $oldValues,
            newValues: $newValues
        );
    }

    /**
     * Registra una acción de auditoría con tipo e identificador explícitos.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function logAction(
        ?User $actor,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        $ip = request()?->ip();
        $userAgent = request()?->userAgent();

        $sanitizedOld = $oldValues !== null ? $this->sanitizeValues($oldValues) : null;
        $sanitizedNew = $newValues !== null ? $this->sanitizeValues($newValues) : null;

        return AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $sanitizedOld,
            'new_values' => $sanitizedNew,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
        ]);
    }

    /**
     * Sanitiza de manera recursiva un conjunto de atributos excluyendo secretos.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function sanitizeValues(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            // Si coincide con alguna clave prohibida, se excluye
            if ($this->isProhibitedKey($normalizedKey)) {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeValues($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    protected function isProhibitedKey(string $key): bool
    {
        foreach (self::PROHIBITED_KEYS as $prohibited) {
            if ($key === $prohibited || str_contains($key, $prohibited)) {
                return true;
            }
        }

        return false;
    }
}
