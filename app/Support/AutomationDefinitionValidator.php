<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\Task;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Validador central del schema de automatizaciones.
 *
 * Usado por store, update y activate (nunca se confía en que "ya era válida").
 * Rechaza fields, operadores, acciones y parámetros fuera del catálogo.
 */
final class AutomationDefinitionValidator
{
    /**
     * @param  array<string, mixed>  $data  name/trigger_type/conditions/actions/owner_id
     * @param  User  $actor  Usuario que configura (para validar scopes de fixed_user/campaign).
     * @return array{conditions: array, actions: array, owner: User}
     *
     * @throws ValidationException
     */
    public static function validate(array $data, User $actor): array
    {
        $trigger = $data['trigger_type'] ?? null;

        if (! is_string($trigger) || ! in_array($trigger, AutomationCatalog::triggers(), true)) {
            throw ValidationException::withMessages([
                'trigger_type' => 'El trigger seleccionado no es válido.',
            ]);
        }

        $owner = self::resolveOwner($data['owner_id'] ?? null, $actor);
        $fields = AutomationCatalog::fieldsForTrigger($trigger);

        return [
            'conditions' => self::validateConditions($data['conditions'] ?? [], $fields),
            'actions' => self::validateActions($data['actions'] ?? [], $trigger, $owner),
            'owner' => $owner,
        ];
    }

    public static function resolveOwner(mixed $ownerId, User $actor): User
    {
        if (! blank($ownerId)) {
            $owner = User::find($ownerId);

            if (! $owner || $owner->status !== 'active') {
                throw ValidationException::withMessages([
                    'owner_id' => 'El responsable debe existir y estar activo.',
                ]);
            }

            if (! DataScope::canAssignUser($actor, $owner->id)) {
                throw ValidationException::withMessages([
                    'owner_id' => 'No puedes asignar ese responsable.',
                ]);
            }

            return $owner;
        }

        if (DataScope::isUnconstrained($actor)) {
            throw ValidationException::withMessages([
                'owner_id' => 'Indica el responsable bajo cuya autoridad operará la automatización.',
            ]);
        }

        return $actor;
    }

    /**
     * @param  array<string, string>  $fields  field => type
     * @return array<int, array{field: string, operator: string, value: mixed}>
     *
     * @throws ValidationException
     */
    public static function validateConditions(mixed $conditions, array $fields): array
    {
        if ($conditions === null || $conditions === []) {
            return [];
        }

        if (! is_array($conditions) || array_is_list($conditions) === false) {
            throw ValidationException::withMessages([
                'conditions' => 'Las condiciones deben ser una lista.',
            ]);
        }

        if (count($conditions) > AutomationCatalog::MAX_CONDITIONS) {
            throw ValidationException::withMessages([
                'conditions' => 'Máximo '.AutomationCatalog::MAX_CONDITIONS.' condiciones por automatización.',
            ]);
        }

        $clean = [];

        foreach (array_values($conditions) as $i => $condition) {
            if (! is_array($condition)) {
                throw ValidationException::withMessages([
                    "conditions.{$i}" => 'Condición inválida.',
                ]);
            }

            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? null;
            $value = $condition['value'] ?? null;

            if (! is_string($field) || ! array_key_exists($field, $fields)) {
                throw ValidationException::withMessages([
                    "conditions.{$i}.field" => 'Campo no permitido para este trigger.',
                ]);
            }

            $type = $fields[$field];
            $allowed = AutomationCatalog::operatorsFor($type);

            if (! is_string($operator) || ! in_array($operator, $allowed, true)) {
                throw ValidationException::withMessages([
                    "conditions.{$i}.operator" => 'Operador no compatible con el campo.',
                ]);
            }

            $clean[] = [
                'field' => $field,
                'operator' => $operator,
                'value' => self::validateConditionValue($i, $type, $operator, $value),
            ];
        }

        return $clean;
    }

    private static function validateConditionValue(int $i, string $type, string $operator, mixed $value): mixed
    {
        if (in_array($operator, ['is_null', 'not_null'], true)) {
            return null;
        }

        if (in_array($operator, ['in', 'not_in'], true)) {
            // La UI envía texto separado por comas; se normaliza a lista.
            if (is_string($value)) {
                $value = array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));
            }

            if (! is_array($value) || $value === [] || count($value) > 20) {
                throw ValidationException::withMessages([
                    "conditions.{$i}.value" => 'Indica una lista de 1 a 20 valores.',
                ]);
            }

            foreach ($value as $candidate) {
                self::assertScalar($i, $type, $candidate);
            }

            return array_values($value);
        }

        if ($operator === 'contains') {
            if (! is_string($value) || trim($value) === '' || mb_strlen($value) > 255) {
                throw ValidationException::withMessages([
                    "conditions.{$i}.value" => 'Indica un texto de 1 a 255 caracteres.',
                ]);
            }

            return $value;
        }

        self::assertScalar($i, $type, $value);

        return $value;
    }

    private static function assertScalar(int $i, string $type, mixed $value): void
    {
        if (is_array($value) || is_object($value) || $value === null || $value === '') {
            throw ValidationException::withMessages([
                "conditions.{$i}.value" => 'Valor inválido para la condición.',
            ]);
        }

        if (in_array($type, ['integer', 'user_id'], true) && ! self::isIntegerish($value)) {
            throw ValidationException::withMessages([
                "conditions.{$i}.value" => 'Se esperaba un número entero.',
            ]);
        }

        if ($type === 'decimal' && ! is_numeric($value)) {
            throw ValidationException::withMessages([
                "conditions.{$i}.value" => 'Se esperaba un número.',
            ]);
        }

        if ($type === 'string' && mb_strlen((string) $value) > 255) {
            throw ValidationException::withMessages([
                "conditions.{$i}.value" => 'Máximo 255 caracteres.',
            ]);
        }

        if ($type === 'user_id') {
            $user = User::find($value);
            if (! $user) {
                throw ValidationException::withMessages([
                    "conditions.{$i}.value" => 'El usuario indicado no existe.',
                ]);
            }
        }
    }

    private static function isIntegerish(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        return is_string($value) && preg_match('/^-?\d+$/', $value) === 1;
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationException
     */
    public static function validateActions(mixed $actions, string $trigger, User $owner): array
    {
        if (! is_array($actions) || array_is_list($actions) === false || $actions === []) {
            throw ValidationException::withMessages([
                'actions' => 'Indica al menos una acción.',
            ]);
        }

        if (count($actions) > AutomationCatalog::MAX_ACTIONS) {
            throw ValidationException::withMessages([
                'actions' => 'Máximo '.AutomationCatalog::MAX_ACTIONS.' acciones por automatización.',
            ]);
        }

        $subject = AutomationCatalog::subjectFor($trigger);
        $clean = [];

        foreach (array_values($actions) as $i => $action) {
            if (! is_array($action)) {
                throw ValidationException::withMessages([
                    "actions.{$i}" => 'Acción inválida.',
                ]);
            }

            $type = $action['type'] ?? null;

            if (! is_string($type) || ! array_key_exists($type, AutomationCatalog::ACTIONS)) {
                throw ValidationException::withMessages([
                    "actions.{$i}.type" => 'Tipo de acción no permitido.',
                ]);
            }

            $clean[] = match ($type) {
                'create_task' => self::validateCreateTask($i, $action, $owner),
                'create_activity' => self::validateCreateActivity($i, $action),
                'assign_owner' => self::validateAssignOwner($i, $action, $subject, $owner),
                'add_to_campaign' => self::validateAddToCampaign($i, $action, $subject, $owner),
            };
        }

        return $clean;
    }

    /** @param  array<string, mixed>  $action */
    private static function validateCreateTask(int $i, array $action, User $owner): array
    {
        $title = $action['title'] ?? null;
        $description = $action['description'] ?? null;
        $priority = $action['priority'] ?? 'medium';
        $dueInDays = $action['due_in_days'] ?? 3;
        $mode = $action['assigned_to_mode'] ?? 'subject_owner';
        $fixedUserId = $action['fixed_user_id'] ?? null;

        if (! is_string($title) || trim($title) === '' || mb_strlen($title) > 255) {
            throw ValidationException::withMessages([
                "actions.{$i}.title" => 'El título es obligatorio (máx. 255).',
            ]);
        }

        if ($description !== null && (! is_string($description) || mb_strlen($description) > 2000)) {
            throw ValidationException::withMessages([
                "actions.{$i}.description" => 'Máximo 2000 caracteres.',
            ]);
        }

        if (! in_array($priority, Task::PRIORITIES, true)) {
            throw ValidationException::withMessages([
                "actions.{$i}.priority" => 'Prioridad no válida.',
            ]);
        }

        if (! self::isIntegerish($dueInDays) || (int) $dueInDays < 0 || (int) $dueInDays > 365) {
            throw ValidationException::withMessages([
                "actions.{$i}.due_in_days" => 'Indica días de 0 a 365.',
            ]);
        }

        if (! in_array($mode, ['subject_owner', 'automation_owner', 'triggering_user', 'fixed_user'], true)) {
            throw ValidationException::withMessages([
                "actions.{$i}.assigned_to_mode" => 'Modo de asignación no válido.',
            ]);
        }

        if ($mode === 'fixed_user') {
            self::assertAssignableUser($i, $fixedUserId, $owner);
        }

        return [
            'type' => 'create_task',
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'due_in_days' => (int) $dueInDays,
            'assigned_to_mode' => $mode,
            'fixed_user_id' => $mode === 'fixed_user' ? (int) $fixedUserId : null,
        ];
    }

    /** @param  array<string, mixed>  $action */
    private static function validateCreateActivity(int $i, array $action): array
    {
        $subject = $action['subject'] ?? null;
        $description = $action['description'] ?? null;
        $actorMode = $action['actor_mode'] ?? 'automation_owner';
        $activityType = $action['activity_type'] ?? 'note';

        if (! is_string($subject) || trim($subject) === '' || mb_strlen($subject) > 255) {
            throw ValidationException::withMessages([
                "actions.{$i}.subject" => 'El asunto es obligatorio (máx. 255).',
            ]);
        }

        if ($description !== null && (! is_string($description) || mb_strlen($description) > 2000)) {
            throw ValidationException::withMessages([
                "actions.{$i}.description" => 'Máximo 2000 caracteres.',
            ]);
        }

        if (! in_array($actorMode, ['automation_owner', 'triggering_user'], true)) {
            throw ValidationException::withMessages([
                "actions.{$i}.actor_mode" => 'Actor no válido.',
            ]);
        }

        // Solo tipos manuales simples; nunca status_change del sistema.
        if (! in_array($activityType, ['note', 'call', 'email'], true)) {
            throw ValidationException::withMessages([
                "actions.{$i}.activity_type" => 'Tipo de actividad no permitido.',
            ]);
        }

        return [
            'type' => 'create_activity',
            'subject' => $subject,
            'description' => $description,
            'actor_mode' => $actorMode,
            'activity_type' => $activityType,
        ];
    }

    /** @param  array<string, mixed>  $action */
    private static function validateAssignOwner(int $i, array $action, ?string $subject, User $owner): array
    {
        if (! in_array($subject, AutomationCatalog::OWNER_ASSIGNABLE, true)) {
            throw ValidationException::withMessages([
                "actions.{$i}.type" => 'Este trigger no admite asignar responsable.',
            ]);
        }

        $mode = $action['target_mode'] ?? 'automation_owner';
        $fixedUserId = $action['fixed_user_id'] ?? null;

        if (! in_array($mode, ['automation_owner', 'triggering_user', 'fixed_user'], true)) {
            throw ValidationException::withMessages([
                "actions.{$i}.target_mode" => 'Destino no válido.',
            ]);
        }

        if ($mode === 'fixed_user') {
            self::assertAssignableUser($i, $fixedUserId, $owner);
        }

        return [
            'type' => 'assign_owner',
            'target_mode' => $mode,
            'fixed_user_id' => $mode === 'fixed_user' ? (int) $fixedUserId : null,
        ];
    }

    /** @param  array<string, mixed>  $action */
    private static function validateAddToCampaign(int $i, array $action, ?string $subject, User $owner): array
    {
        if (! in_array($subject, AutomationCatalog::CAMPAIGN_ELIGIBLE, true)) {
            throw ValidationException::withMessages([
                "actions.{$i}.type" => 'Solo contactos o leads pueden agregarse a campaña.',
            ]);
        }

        $campaignId = $action['campaign_id'] ?? null;
        $campaign = $campaignId ? Campaign::find($campaignId) : null;

        if (! $campaign) {
            throw ValidationException::withMessages([
                "actions.{$i}.campaign_id" => 'La campaña indicada no existe.',
            ]);
        }

        if (! $owner->can('update', $campaign)) {
            throw ValidationException::withMessages([
                "actions.{$i}.campaign_id" => 'Sin acceso a la campaña indicada.',
            ]);
        }

        return ['type' => 'add_to_campaign', 'campaign_id' => (int) $campaign->id];
    }

    private static function assertAssignableUser(int $i, mixed $userId, User $owner): void
    {
        $user = $userId ? User::find($userId) : null;

        if (! $user || $user->status !== 'active') {
            throw ValidationException::withMessages([
                "actions.{$i}.fixed_user_id" => 'El usuario debe existir y estar activo.',
            ]);
        }

        // Sin escalamiento: el fijo debe estar en el alcance del owner.
        if (! in_array((int) $user->id, DataScope::filterableUserIds($owner), true)) {
            throw ValidationException::withMessages([
                "actions.{$i}.fixed_user_id" => 'Usuario fuera de tu alcance.',
            ]);
        }
    }
}
