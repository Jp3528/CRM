<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Task;
use App\Models\Ticket;

/**
 * Catálogo central de automatizaciones internas.
 *
 * Todo lo configurable (triggers, subjects, condition fields, operadores y
 * acciones) vive aquí como whitelist. Nada se acepta desde requests fuera
 * de este catálogo: sin clases PHP, sin métodos, sin SQL, sin código.
 */
final class AutomationCatalog
{
    public const MAX_CONDITIONS = 10;

    public const MAX_ACTIONS = 5;

    public const MAX_DEPTH = 5;

    /** Clave segura de subject => modelo. */
    public const SUBJECTS = [
        'lead' => Lead::class,
        'opportunity' => Opportunity::class,
        'task' => Task::class,
        'ticket' => Ticket::class,
        'quote' => Quote::class,
        'sale' => Sale::class,
        'invoice' => Invoice::class,
        'campaign' => Campaign::class,
        'contact' => Contact::class,
    ];

    /**
     * Trigger => subject + contexto disponible + si es cambio de estado.
     *
     * @var array<string, array{subject: string, label: string}>
     */
    public const TRIGGERS = [
        'lead.created' => ['subject' => 'lead', 'label' => 'Lead creado'],
        'lead.status_changed' => ['subject' => 'lead', 'label' => 'Lead cambia de estado'],
        'contact.created' => ['subject' => 'contact', 'label' => 'Contacto creado'],
        'opportunity.stage_changed' => ['subject' => 'opportunity', 'label' => 'Oportunidad cambia de etapa'],
        'opportunity.won' => ['subject' => 'opportunity', 'label' => 'Oportunidad ganada'],
        'opportunity.lost' => ['subject' => 'opportunity', 'label' => 'Oportunidad perdida'],
        'task.completed' => ['subject' => 'task', 'label' => 'Tarea completada'],
        'ticket.status_changed' => ['subject' => 'ticket', 'label' => 'Ticket cambia de estado'],
        'quote.status_changed' => ['subject' => 'quote', 'label' => 'Cotización cambia de estado'],
        'sale.status_changed' => ['subject' => 'sale', 'label' => 'Venta cambia de estado'],
        'invoice.status_changed' => ['subject' => 'invoice', 'label' => 'Factura cambia de estado'],
        'campaign.status_changed' => ['subject' => 'campaign', 'label' => 'Campaña cambia de estado'],
    ];

    /**
     * Campos condicionables por subject. previous_* solo existen en el
     * contexto cuando el trigger es de cambio.
     *
     * @var array<string, array<string, string>> subject => field => type
     *      Tipos: string|integer|decimal|user_id
     */
    public const FIELDS = [
        'lead' => [
            'status' => 'string',
            'source' => 'string',
            'score' => 'integer',
            'estimated_value' => 'decimal',
            'owner_id' => 'user_id',
            'previous_status' => 'string',
        ],
        'contact' => [
            'status' => 'string',
            'owner_id' => 'user_id',
            'company_id' => 'integer',
        ],
        'opportunity' => [
            'status' => 'string',
            'stage_id' => 'integer',
            'amount' => 'decimal',
            'probability' => 'integer',
            'owner_id' => 'user_id',
            'previous_status' => 'string',
            'previous_stage_id' => 'integer',
        ],
        'task' => [
            'status' => 'string',
            'priority' => 'string',
            'assigned_to' => 'user_id',
            'previous_status' => 'string',
        ],
        'ticket' => [
            'status' => 'string',
            'priority' => 'string',
            'category_id' => 'integer',
            'assigned_to' => 'user_id',
            'previous_status' => 'string',
        ],
        'quote' => [
            'status' => 'string',
            'total' => 'decimal',
            'owner_id' => 'user_id',
            'previous_status' => 'string',
        ],
        'sale' => [
            'status' => 'string',
            'total' => 'decimal',
            'owner_id' => 'user_id',
            'previous_status' => 'string',
        ],
        'invoice' => [
            'status' => 'string',
            'total' => 'decimal',
            'owner_id' => 'user_id',
            'previous_status' => 'string',
        ],
        'campaign' => [
            'status' => 'string',
            'type' => 'string',
            'owner_id' => 'user_id',
            'previous_status' => 'string',
        ],
    ];

    /** Campos previous_* por trigger (los que el contexto proporciona). */
    public const TRIGGER_PREVIOUS_FIELDS = [
        'lead.created' => [],
        'lead.status_changed' => ['previous_status'],
        'contact.created' => [],
        'opportunity.stage_changed' => ['previous_stage_id', 'previous_status'],
        'opportunity.won' => [],
        'opportunity.lost' => [],
        'task.completed' => ['previous_status'],
        'ticket.status_changed' => ['previous_status'],
        'quote.status_changed' => ['previous_status'],
        'sale.status_changed' => ['previous_status'],
        'invoice.status_changed' => ['previous_status'],
        'campaign.status_changed' => ['previous_status'],
    ];

    public const OPERATORS = [
        'equals',
        'not_equals',
        'greater_than',
        'greater_or_equal',
        'less_than',
        'less_or_equal',
        'in',
        'not_in',
        'contains',
        'is_null',
        'not_null',
    ];

    /** Operadores compatibles por tipo de campo. */
    public const OPERATORS_BY_TYPE = [
        'string' => ['equals', 'not_equals', 'in', 'not_in', 'contains', 'is_null', 'not_null'],
        'integer' => ['equals', 'not_equals', 'greater_than', 'greater_or_equal', 'less_than', 'less_or_equal', 'in', 'not_in', 'is_null', 'not_null'],
        'decimal' => ['equals', 'not_equals', 'greater_than', 'greater_or_equal', 'less_than', 'less_or_equal', 'in', 'not_in', 'is_null', 'not_null'],
        'user_id' => ['equals', 'not_equals', 'in', 'not_in', 'is_null', 'not_null'],
    ];

    public const ACTIONS = [
        'create_task' => 'Crear tarea',
        'create_activity' => 'Registrar actividad',
        'assign_owner' => 'Asignar responsable',
        'add_to_campaign' => 'Agregar a campaña',
    ];

    /** Subjects que admiten assign_owner (tienen owner_id real). */
    public const OWNER_ASSIGNABLE = [
        'lead', 'opportunity', 'quote', 'sale', 'invoice', 'campaign', 'contact',
    ];

    /** Subjects admitidos por add_to_campaign. */
    public const CAMPAIGN_ELIGIBLE = ['contact', 'lead'];

    /** @return array<int, string> */
    public static function triggers(): array
    {
        return array_keys(self::TRIGGERS);
    }

    public static function subjectFor(string $trigger): ?string
    {
        return self::TRIGGERS[$trigger]['subject'] ?? null;
    }

    public static function triggerLabel(string $trigger): string
    {
        return self::TRIGGERS[$trigger]['label'] ?? $trigger;
    }

    public static function classForSubject(string $subject): ?string
    {
        return self::SUBJECTS[$subject] ?? null;
    }

    public static function subjectForClass(string $class): ?string
    {
        $key = array_search($class, self::SUBJECTS, true);

        return $key === false ? null : $key;
    }

    /** @return array<string, string> field => type */
    public static function fieldsForTrigger(string $trigger): array
    {
        $subject = self::subjectFor($trigger);

        if ($subject === null) {
            return [];
        }

        $fields = self::FIELDS[$subject] ?? [];
        $allowedPrevious = self::TRIGGER_PREVIOUS_FIELDS[$trigger] ?? [];

        return array_filter(
            $fields,
            fn ($type, $field) => ! str_starts_with($field, 'previous_') || in_array($field, $allowedPrevious, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /** @return array<int, string> */
    public static function operatorsFor(string $fieldType): array
    {
        return self::OPERATORS_BY_TYPE[$fieldType] ?? [];
    }
}
