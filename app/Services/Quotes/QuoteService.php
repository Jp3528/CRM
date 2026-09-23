<?php

namespace App\Services\Quotes;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuoteService
{
    public function __construct(private QuoteCalculator $calculator) {}

    /**
     * Crea cotización + líneas atómicamente. Los totales del frontend se
     * ignoran: el backend recalcula todo con QuoteCalculator.
     *
     * @param  array<string, mixed>  $data  Datos validados del FormRequest.
     *
     * @throws ValidationException
     */
    public function create(array $data, User $actor): Quote
    {
        return DB::transaction(function () use ($data, $actor) {
            $data['owner_id'] = DataScope::normalizeOwnerId($actor, $data['owner_id'] ?? null);
            $this->assertScope($data, $actor);
            $this->assertCoherence($data);

            $items = $this->normalizeItems($data['items']);
            $calculation = $this->calculator->calculate($items);

            $quote = Quote::create([
                'number' => 'TMP-'.Str::uuid(),
                'company_id' => $data['company_id'],
                'contact_id' => $data['contact_id'] ?? null,
                'opportunity_id' => $data['opportunity_id'] ?? null,
                'owner_id' => $data['owner_id'] ?? null,
                'status' => 'draft',
                'currency' => $data['currency'],
                'issue_date' => $data['issue_date'] ?? today()->toDateString(),
                'valid_until' => $data['valid_until'] ?? null,
                'subtotal' => $calculation['subtotal'],
                'discount_total' => $calculation['discount_total'],
                'tax_total' => $calculation['tax_total'],
                'total' => $calculation['total'],
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            // Numeración legible derivada del ID: única y segura en concurrencia.
            $quote->update([
                'number' => sprintf('Q-%s-%06d', now()->format('Y'), $quote->id),
            ]);

            $this->syncItems($quote, $items, $calculation['lines']);
            $this->linkContact($quote);
            $this->log($quote, $actor, "Cotización {$quote->number} creada");

            return $quote->fresh();
        });
    }

    /**
     * Actualiza cabecera + reemplaza líneas atómicamente. Solo draft/sent.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(Quote $quote, array $data, User $actor): Quote
    {
        return DB::transaction(function () use ($quote, $data, $actor) {
            $quote = Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail();

            if (! in_array($quote->status, Quote::EDITABLE_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden editar cotizaciones en borrador o enviadas.',
                ]);
            }

            $data['owner_id'] = DataScope::normalizeOwnerId($actor, $data['owner_id'] ?? null, $quote->owner_id);
            $this->assertScope($data, $actor, $quote->owner_id);
            $this->assertCoherence($data);

            $items = $this->normalizeItems($data['items']);
            $calculation = $this->calculator->calculate($items);

            $quote->update([
                'company_id' => $data['company_id'],
                'contact_id' => $data['contact_id'] ?? null,
                'opportunity_id' => $data['opportunity_id'] ?? null,
                'owner_id' => $data['owner_id'] ?? null,
                'currency' => $data['currency'],
                'issue_date' => $data['issue_date'] ?? $quote->issue_date,
                'valid_until' => $data['valid_until'] ?? null,
                'subtotal' => $calculation['subtotal'],
                'discount_total' => $calculation['discount_total'],
                'tax_total' => $calculation['tax_total'],
                'total' => $calculation['total'],
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $quote->items()->delete();
            $this->syncItems($quote, $items, $calculation['lines']);
            $this->linkContact($quote);

            return $quote->fresh();
        });
    }

    /**
     * Cambia el estado (draft→sent/accepted/rejected, sent→accepted/rejected).
     * accepted/rejected son terminales; expired es calculado, no transición.
     *
     * @throws ValidationException
     */
    public function transition(Quote $quote, string $to, User $actor): Quote
    {
        return DB::transaction(function () use ($quote, $to, $actor) {
            $quote = Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail();

            $allowed = [
                'draft' => ['sent', 'accepted', 'rejected'],
                'sent' => ['accepted', 'rejected'],
            ];

            if (! in_array($to, $allowed[$quote->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => "No se puede pasar de {$quote->status} a {$to}.",
                ]);
            }

            if ($to === 'sent' && $quote->is_expired) {
                throw ValidationException::withMessages([
                    'status' => 'La cotización está vencida. Extiende la vigencia antes de enviarla.',
                ]);
            }

            $quote->update([
                'status' => $to,
                'accepted_at' => $to === 'accepted' ? now() : null,
                'rejected_at' => $to === 'rejected' ? now() : null,
            ]);

            $labels = ['sent' => 'enviada', 'accepted' => 'aceptada', 'rejected' => 'rechazada'];
            $this->log($quote, $actor, "Cotización {$quote->number} {$labels[$to]}");

            return $quote->fresh();
        });
    }

    /**
     * Coherencia comercial: contacto y oportunidad deben pertenecer a la empresa.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function assertCoherence(array $data): void
    {
        if (! empty($data['contact_id'])) {
            $contact = Contact::findOrFail($data['contact_id']);
            if ($contact->company_id !== null && (int) $contact->company_id !== (int) $data['company_id']) {
                throw ValidationException::withMessages([
                    'contact_id' => 'El contacto pertenece a otra empresa.',
                ]);
            }
        }

        if (! empty($data['opportunity_id'])) {
            $opportunity = Opportunity::findOrFail($data['opportunity_id']);
            if ($opportunity->company_id !== null && (int) $opportunity->company_id !== (int) $data['company_id']) {
                throw ValidationException::withMessages([
                    'opportunity_id' => 'La oportunidad pertenece a otra empresa.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertScope(array $data, User $actor, ?int $currentOwnerId = null): void
    {
        DataScope::assertVisibleId($actor, Company::class, $data['company_id'] ?? null);
        DataScope::assertVisibleId($actor, Contact::class, $data['contact_id'] ?? null);
        DataScope::assertVisibleId($actor, Opportunity::class, $data['opportunity_id'] ?? null);
        DataScope::assertCanAssignUser($actor, $data['owner_id'] ?? null, $currentOwnerId);
    }

    /**
     * Resuelve el snapshot de cada línea: valores explícitos ganan; lo ausente
     * se precarga del producto (nombre, unidad, precio, impuesto). Rechaza
     * productos inactivos y líneas manuales sin descripción, ANTES de calcular.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationException
     */
    private function normalizeItems(array $items): array
    {
        $normalized = [];

        foreach (array_values($items) as $position => $item) {
            $product = null;
            if (! empty($item['product_id'])) {
                $product = Product::findOrFail($item['product_id']);
                if ($product->status !== 'active') {
                    throw ValidationException::withMessages([
                        "items.{$position}.product_id" => "El producto {$product->sku} está inactivo.",
                    ]);
                }
            }

            if (! $product && blank($item['description'] ?? null)) {
                throw ValidationException::withMessages([
                    "items.{$position}.description" => 'La línea manual requiere descripción.',
                ]);
            }

            $normalized[] = [
                'product_id' => $product?->id,
                'description' => $item['description'] ?? $product->name,
                'unit' => $item['unit'] ?? $product?->unit ?? 'unit',
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'] ?? $product->price,
                'discount_type' => $item['discount_type'] ?? 'none',
                'discount_value' => $item['discount_value'] ?? 0,
                'tax_rate' => $item['tax_rate'] ?? $product?->tax_rate ?? '0.00',
            ];
        }

        return $normalized;
    }

    /**
     * Crea las líneas con snapshot (descripción/precio/impuesto congelados).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, string>>  $calculated
     */
    private function syncItems(Quote $quote, array $items, array $calculated): void
    {
        foreach ($items as $position => $item) {
            $calc = $calculated[$position];

            $quote->items()->create([
                'product_id' => $item['product_id'],
                'position' => $position,
                'description' => $item['description'],
                'unit' => $item['unit'],
                'quantity' => $calc['quantity'],
                'unit_price' => $item['unit_price'],
                'discount_type' => $calc['discount_type'],
                'discount_value' => $calc['discount_value'],
                'tax_rate' => $item['tax_rate'],
                'subtotal' => $calc['subtotal'],
                'discount_amount' => $calc['discount_amount'],
                'tax_amount' => $calc['tax_amount'],
                'total' => $calc['total'],
            ]);
        }
    }

    /**
     * Vincula el contacto a la empresa si aún no tiene (explícito, misma empresa).
     */
    private function linkContact(Quote $quote): void
    {
        if ($quote->contact && $quote->contact->company_id === null) {
            $quote->contact->update(['company_id' => $quote->company_id]);
        }
    }

    private function log(Quote $quote, User $actor, string $subject): void
    {
        $target = $quote->opportunity ?? $quote->company;

        $target?->activities()->create([
            'type' => 'status_change',
            'subject' => $subject,
            'description' => "{$subject} por {$actor->name}. Total: {$quote->total} {$quote->currency}.",
            'status' => 'completed',
            'completed_at' => now(),
            'user_id' => $actor->id,
        ]);
    }
}
