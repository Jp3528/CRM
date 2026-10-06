<?php

namespace App\Services\Exports;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public const SUPPORTED_MODULES = [
        'companies',
        'contacts',
        'leads',
        'opportunities',
        'tasks',
        'quotes',
        'sales',
        'invoices',
        'tickets',
    ];

    /**
     * Devuelve el nombre amigable en español para el módulo.
     */
    public static function moduleLabel(string $module): string
    {
        return match ($module) {
            'companies' => 'Empresas',
            'contacts' => 'Contactos',
            'leads' => 'Leads',
            'opportunities' => 'Oportunidades',
            'tasks' => 'Tareas',
            'quotes' => 'Cotizaciones',
            'sales' => 'Ventas',
            'invoices' => 'Facturas',
            'tickets' => 'Tickets',
            default => ucfirst($module),
        };
    }

    /**
     * Genera la descarga en streaming CSV de un módulo con DataScope y filtros aplicados.
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportModule(string $module, User $user, array $filters = []): StreamedResponse
    {
        if (! in_array($module, self::SUPPORTED_MODULES, true)) {
            throw new InvalidArgumentException("Módulo '{$module}' no admitido para exportación.");
        }

        $query = $this->buildQuery($module, $user, $filters);
        $headers = $this->getHeaders($module);
        $rowTransformer = $this->getRowTransformer($module);
        $filename = "export_{$module}_".now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query, $headers, $rowTransformer) {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            // UTF-8 BOM para compatibilidad universal con Excel en Windows y Mac
            fwrite($output, "\xEF\xBB\xBF");

            // Cabeceras de columna
            fputcsv($output, $headers);

            // Cursor para streaming memoria O(1)
            foreach ($query->cursor() as $record) {
                $rawRow = $rowTransformer($record);
                $sanitizedRow = array_map([self::class, 'sanitizeCell'], $rawRow);
                fputcsv($output, $sanitizedRow);
                fflush($output);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Construye la consulta filtrada respetando estrictamente el DataScope del usuario.
     *
     * @param  array<string, mixed>  $filters
     */
    public function buildQuery(string $module, User $user, array $filters): Builder
    {
        return match ($module) {
            'companies' => $this->buildCompaniesQuery($user, $filters),
            'contacts' => $this->buildContactsQuery($user, $filters),
            'leads' => $this->buildLeadsQuery($user, $filters),
            'opportunities' => $this->buildOpportunitiesQuery($user, $filters),
            'tasks' => $this->buildTasksQuery($user, $filters),
            'quotes' => $this->buildQuotesQuery($user, $filters),
            'sales' => $this->buildSalesQuery($user, $filters),
            'invoices' => $this->buildInvoicesQuery($user, $filters),
            'tickets' => $this->buildTicketsQuery($user, $filters),
            default => throw new InvalidArgumentException("Módulo no soportado: {$module}"),
        };
    }

    protected function buildCompaniesQuery(User $user, array $filters): Builder
    {
        return Company::query()
            ->visibleTo($user)
            ->with(['owner:id,name'])
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->industry($filters['industry'] ?? null)
            ->country($filters['country'] ?? null)
            ->ownedBy($filters['owner_id'] ?? null)
            ->latest('id');
    }

    protected function buildContactsQuery(User $user, array $filters): Builder
    {
        return Contact::query()
            ->visibleTo($user)
            ->with([
                'company' => fn ($q) => $q->visibleTo($user)->select('id', 'trade_name', 'owner_id'),
                'owner:id,name',
            ])
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->forCompany($filters['company_id'] ?? null)
            ->department($filters['department'] ?? null)
            ->ownedBy($filters['owner_id'] ?? null)
            ->latest('id');
    }

    protected function buildLeadsQuery(User $user, array $filters): Builder
    {
        return Lead::query()
            ->visibleTo($user)
            ->with(['owner:id,name'])
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->source($filters['source'] ?? null)
            ->ownedBy($filters['owner_id'] ?? null)
            ->scoreBetween($filters['score_min'] ?? null, $filters['score_max'] ?? null)
            ->converted($filters['converted'] ?? null)
            ->latest('id');
    }

    protected function buildOpportunitiesQuery(User $user, array $filters): Builder
    {
        return Opportunity::query()
            ->visibleTo($user)
            ->with([
                'company' => fn ($q) => $q->visibleTo($user)->select('id', 'trade_name', 'owner_id'),
                'contact' => fn ($q) => $q->visibleTo($user)->select('id', 'first_name', 'last_name', 'company_id', 'owner_id'),
                'pipeline:id,name', 'stage:id,name', 'owner:id,name',
            ])
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->pipeline($filters['pipeline_id'] ?? null)
            ->stage($filters['pipeline_stage_id'] ?? null)
            ->ownedBy($filters['owner_id'] ?? null)
            ->forCompany($filters['company_id'] ?? null)
            ->amountBetween($filters['amount_min'] ?? null, $filters['amount_max'] ?? null)
            ->closeBetween($filters['close_from'] ?? null, $filters['close_to'] ?? null)
            ->latest('id');
    }

    protected function buildTasksQuery(User $user, array $filters): Builder
    {
        return Task::query()
            ->visibleTo($user)
            ->with(['assignee:id,name', 'creator:id,name'])
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->priority($filters['priority'] ?? null)
            ->assignedTo($filters['assigned_to'] ?? null)
            ->relatedType($filters['related_type'] ?? null)
            ->due($filters['due'] ?? null)
            ->latest('id');
    }

    protected function buildQuotesQuery(User $user, array $filters): Builder
    {
        return Quote::query()
            ->visibleTo($user)
            ->with([
                'company' => fn ($q) => $q->visibleTo($user)->select('id', 'trade_name', 'owner_id'),
                'contact' => fn ($q) => $q->visibleTo($user)->select('id', 'first_name', 'last_name', 'company_id', 'owner_id'),
                'opportunity' => fn ($q) => $q->visibleTo($user)->select('id', 'name', 'owner_id'),
                'owner:id,name',
            ])
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->ownedBy($filters['owner_id'] ?? null)
            ->forCompany($filters['company_id'] ?? null)
            ->currency($filters['currency'] ?? null)
            ->issuedBetween($filters['issued_from'] ?? null, $filters['issued_to'] ?? null)
            ->latest('id');
    }

    protected function buildSalesQuery(User $user, array $filters): Builder
    {
        return Sale::query()
            ->visibleTo($user)
            ->with([
                'company' => fn ($q) => $q->visibleTo($user)->select('id', 'trade_name', 'owner_id'),
                'contact' => fn ($q) => $q->visibleTo($user)->select('id', 'first_name', 'last_name', 'company_id', 'owner_id'),
                'opportunity' => fn ($q) => $q->visibleTo($user)->select('id', 'name', 'owner_id'),
                'quote' => fn ($q) => $q->visibleTo($user)->select('id', 'number', 'owner_id'),
                'owner:id,name',
            ])
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->ownedBy($filters['owner_id'] ?? null)
            ->forCompany($filters['company_id'] ?? null)
            ->currency($filters['currency'] ?? null)
            ->soldBetween($filters['sold_from'] ?? null, $filters['sold_to'] ?? null)
            ->latest('id');
    }

    protected function buildInvoicesQuery(User $user, array $filters): Builder
    {
        return Invoice::query()
            ->visibleTo($user)
            ->with([
                'sale' => fn ($q) => $q->visibleTo($user)->select('id', 'number', 'owner_id'),
                'company' => fn ($q) => $q->visibleTo($user)->select('id', 'trade_name', 'owner_id'),
                'contact' => fn ($q) => $q->visibleTo($user)->select('id', 'first_name', 'last_name', 'company_id', 'owner_id'),
                'owner:id,name',
            ])
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->ownedBy($filters['owner_id'] ?? null)
            ->forCompany($filters['company_id'] ?? null)
            ->currency($filters['currency'] ?? null)
            ->issuedBetween($filters['issued_from'] ?? null, $filters['issued_to'] ?? null)
            ->latest('id');
    }

    protected function buildTicketsQuery(User $user, array $filters): Builder
    {
        $status = $filters['status'] ?? null;
        $priority = $filters['priority'] ?? null;
        $assigned = $filters['assigned_to'] ?? null;

        if (! empty($filters['preset'])) {
            match ($filters['preset']) {
                'mine' => $assigned = (string) $user->id,
                'unassigned' => $assigned = 'unassigned',
                'open' => $status = 'open',
                'pending' => $status = 'pending',
                'urgent' => $priority = 'urgent',
                default => null,
            };
        }

        return Ticket::query()
            ->visibleTo($user)
            ->with([
                'company' => fn ($q) => $q->visibleTo($user)->select('id', 'trade_name', 'owner_id'),
                'contact' => fn ($q) => $q->visibleTo($user)->select('id', 'first_name', 'last_name', 'company_id', 'owner_id'),
                'assignedTo:id,name', 'category:id,name',
            ])
            ->search($filters['search'] ?? null)
            ->status($status)
            ->priority($priority)
            ->category($filters['category_id'] ?? null)
            ->assignedTo($assigned)
            ->forCompany($filters['company_id'] ?? null)
            ->channel($filters['channel'] ?? null)
            ->latest('id');
    }

    /**
     * Encabezados de columna para cada módulo.
     *
     * @return string[]
     */
    public function getHeaders(string $module): array
    {
        return match ($module) {
            'companies' => [
                'ID', 'Nombre Comercial', 'Razón Social', 'NIT / RUC',
                'Email', 'Teléfono', 'Sitio Web', 'Industria',
                'Tamaño', 'Dirección', 'Ciudad', 'Región',
                'País', 'Estado', 'Responsable', 'Fecha Creación',
            ],
            'contacts' => [
                'ID', 'Nombre', 'Apellido', 'Email',
                'Teléfono', 'Móvil', 'Empresa', 'Cargo',
                'Departamento', 'Estado', 'Responsable', 'Fecha Creación',
            ],
            'leads' => [
                'ID', 'Nombre', 'Apellido', 'Empresa',
                'Email', 'Teléfono', 'Fuente', 'Estado',
                'Score', 'Valor Estimado', 'Moneda', 'Convertido',
                'Responsable', 'Fecha Creación',
            ],
            'opportunities' => [
                'ID', 'Nombre', 'Empresa', 'Contacto',
                'Pipeline', 'Etapa', 'Monto', 'Moneda',
                'Probabilidad (%)', 'Cierre Estimado', 'Estado',
                'Responsable', 'Fecha Creación',
            ],
            'tasks' => [
                'ID', 'Título', 'Tipo', 'Prioridad',
                'Estado', 'Vence', 'Asignado a', 'Creado por',
                'Relacionado con', 'Fecha Creación',
            ],
            'quotes' => [
                'ID', 'Número Cotización', 'Empresa', 'Contacto',
                'Oportunidad', 'Moneda', 'Subtotal', 'Impuestos',
                'Total', 'Estado', 'Vence', 'Responsable', 'Fecha Creación',
            ],
            'sales' => [
                'ID', 'Número Venta', 'Empresa', 'Contacto',
                'Cotización Origen', 'Moneda', 'Subtotal', 'Impuestos',
                'Total', 'Estado', 'Fecha Venta', 'Responsable', 'Fecha Creación',
            ],
            'invoices' => [
                'ID', 'Número Factura', 'Venta Origen', 'Empresa',
                'Contacto', 'Moneda', 'Subtotal', 'Impuestos',
                'Total', 'Estado', 'Fecha Emisión', 'Fecha Vencimiento',
                'Responsable', 'Fecha Creación',
            ],
            'tickets' => [
                'ID', 'Número Ticket', 'Título', 'Empresa',
                'Contacto', 'Categoría', 'Canal', 'Prioridad',
                'Estado', 'Asignado a', 'Fecha Creación', 'Última Actualización',
            ],
            default => throw new InvalidArgumentException("Encabezados no configurados para: {$module}"),
        };
    }

    /**
     * Mapeador de modelo a fila cruda de exportación.
     */
    public function getRowTransformer(string $module): \Closure
    {
        return match ($module) {
            'companies' => fn (Company $c) => [
                $c->id,
                $c->trade_name,
                $c->legal_name ?? '',
                $c->tax_id ?? '',
                $c->email ?? '',
                $c->phone ?? '',
                $c->website ?? '',
                $c->industry ?? '',
                $c->company_size ?? '',
                $c->address ?? '',
                $c->city ?? '',
                $c->region ?? '',
                $c->country ?? '',
                $c->status,
                $c->owner?->name ?? 'Sin asignar',
                $c->created_at?->format('Y-m-d H:i:s') ?? '',
            ],
            'contacts' => fn (Contact $c) => [
                $c->id,
                $c->first_name,
                $c->last_name,
                $c->email ?? '',
                $c->phone ?? '',
                $c->mobile ?? '',
                $c->company?->trade_name ?? '',
                $c->job_title ?? '',
                $c->department ?? '',
                $c->status,
                $c->owner?->name ?? 'Sin asignar',
                $c->created_at?->format('Y-m-d H:i:s') ?? '',
            ],
            'leads' => fn (Lead $l) => [
                $l->id,
                $l->first_name,
                $l->last_name ?? '',
                $l->company_name ?? '',
                $l->email ?? '',
                $l->phone ?? '',
                $l->source,
                $l->status,
                $l->score,
                $l->estimated_value ?? '0.00',
                $l->currency ?? 'USD',
                $l->converted_at ? 'Sí' : 'No',
                $l->owner?->name ?? 'Sin asignar',
                $l->created_at?->format('Y-m-d H:i:s') ?? '',
            ],
            'opportunities' => fn (Opportunity $o) => [
                $o->id,
                $o->name,
                $o->company?->trade_name ?? '',
                $o->contact ? "{$o->contact->first_name} {$o->contact->last_name}" : '',
                $o->pipeline?->name ?? '',
                $o->stage?->name ?? '',
                $o->amount,
                $o->currency,
                $o->probability,
                $o->expected_close_date?->format('Y-m-d') ?? '',
                $o->status,
                $o->owner?->name ?? 'Sin asignar',
                $o->created_at?->format('Y-m-d H:i:s') ?? '',
            ],
            'tasks' => fn (Task $t) => [
                $t->id,
                $t->title,
                $t->type ?? 'general',
                $t->priority,
                $t->status,
                $t->due_date?->format('Y-m-d H:i') ?? '',
                $t->assignee?->name ?? 'Sin asignar',
                $t->creator?->name ?? 'Sistema',
                $t->taskable_type ? class_basename($t->taskable_type).' #'.$t->taskable_id : 'Ninguno',
                $t->created_at?->format('Y-m-d H:i:s') ?? '',
            ],
            'quotes' => fn (Quote $q) => [
                $q->id,
                $q->number,
                $q->company?->trade_name ?? '',
                $q->contact ? "{$q->contact->first_name} {$q->contact->last_name}" : '',
                $q->opportunity?->name ?? '',
                $q->currency,
                $q->subtotal,
                $q->tax,
                $q->total,
                $q->status,
                $q->expires_at?->format('Y-m-d') ?? '',
                $q->owner?->name ?? 'Sin asignar',
                $q->created_at?->format('Y-m-d H:i:s') ?? '',
            ],
            'sales' => fn (Sale $s) => [
                $s->id,
                $s->number,
                $s->company?->trade_name ?? '',
                $s->contact ? "{$s->contact->first_name} {$s->contact->last_name}" : '',
                $s->quote?->number ?? '',
                $s->currency,
                $s->subtotal,
                $s->tax,
                $s->total,
                $s->status,
                $s->sold_at?->format('Y-m-d') ?? '',
                $s->owner?->name ?? 'Sin asignar',
                $s->created_at?->format('Y-m-d H:i:s') ?? '',
            ],
            'invoices' => fn (Invoice $i) => [
                $i->id,
                $i->number,
                $i->sale?->number ?? '',
                $i->company?->trade_name ?? '',
                $i->contact ? "{$i->contact->first_name} {$i->contact->last_name}" : '',
                $i->currency,
                $i->subtotal,
                $i->tax,
                $i->total,
                $i->status,
                $i->issued_at?->format('Y-m-d') ?? '',
                $i->due_at?->format('Y-m-d') ?? '',
                $i->owner?->name ?? 'Sin asignar',
                $i->created_at?->format('Y-m-d H:i:s') ?? '',
            ],
            'tickets' => fn (Ticket $t) => [
                $t->id,
                $t->ticket_number,
                $t->title,
                $t->company?->trade_name ?? '',
                $t->contact ? "{$t->contact->first_name} {$t->contact->last_name}" : '',
                $t->category?->name ?? 'Sin categoría',
                $t->channel ?? 'web',
                $t->priority,
                $t->status,
                $t->assignedTo?->name ?? 'Sin asignar',
                $t->created_at?->format('Y-m-d H:i:s') ?? '',
                $t->updated_at?->format('Y-m-d H:i:s') ?? '',
            ],
            default => throw new InvalidArgumentException("Transformador no configurado para: {$module}"),
        };
    }

    /**
     * Sanitiza celdas neutralizando inyecciones de fórmulas CSV en Excel.
     */
    public static function sanitizeCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $str = (string) $value;
        $trimmed = ltrim($str);

        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$str;
        }

        return $str;
    }
}
