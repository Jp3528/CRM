<?php

namespace App\Services\Sales;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaleCreationService
{
    public function __construct(private SaleCalculator $calculator) {}

    /**
     * Convierte una cotización aceptada en venta (1 Quote → máximo 1 Sale).
     * Copia snapshots del QuoteItem (nunca del catálogo actual) y los totales
     * exactos de la cotización. Todo transaccional con lock anti-races.
     *
     * @throws ValidationException
     */
    public function fromQuote(Quote $quote, User $actor, array $data = []): Sale
    {
        return DB::transaction(function () use ($quote, $actor, $data) {
            $quote = Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            abort_unless(DataScope::canViewModel($actor, $quote), 403);

            if ($quote->trashed()) {
                throw ValidationException::withMessages([
                    'quote' => 'No se puede convertir una cotización eliminada.',
                ]);
            }

            if ($quote->status !== 'accepted') {
                throw ValidationException::withMessages([
                    'quote' => 'Solo se pueden convertir cotizaciones aceptadas.',
                ]);
            }

            if ($quote->sale()->exists()) {
                throw ValidationException::withMessages([
                    'quote' => 'Esta cotización ya generó una venta.',
                ]);
            }
            DataScope::assertCanAssignUser($actor, $data['owner_id'] ?? null, $quote->owner_id);

            $sale = Sale::create([
                'number' => 'TMP-'.Str::uuid(),
                'quote_id' => $quote->id,
                'company_id' => $quote->company_id,
                'contact_id' => $quote->contact_id,
                'opportunity_id' => $quote->opportunity_id,
                'owner_id' => $data['owner_id'] ?? $quote->owner_id,
                'status' => 'draft',
                'currency' => $quote->currency,
                'sale_date' => $data['sale_date'] ?? today()->toDateString(),
                'subtotal' => $quote->subtotal,
                'discount_total' => $quote->discount_total,
                'tax_total' => $quote->tax_total,
                'total' => $quote->total,
                'notes' => $data['notes'] ?? null,
            ]);

            $sale->update([
                'number' => sprintf('S-%s-%06d', now()->format('Y'), $sale->id),
            ]);

            foreach ($quote->items()->orderBy('position')->get() as $position => $item) {
                $sale->items()->create([
                    'product_id' => $item->product_id,
                    'position' => $position,
                    'sku' => $item->product?->sku,
                    'description' => $item->description,
                    'unit' => $item->unit,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_type' => $item->discount_type,
                    'discount_value' => $item->discount_value,
                    'tax_rate' => $item->tax_rate,
                    'subtotal' => $item->subtotal,
                    'discount_amount' => $item->discount_amount,
                    'tax_amount' => $item->tax_amount,
                    'total' => $item->total,
                ]);
            }

            $this->log($sale, $actor, "Venta {$sale->number} creada desde cotización {$quote->number}");

            return $sale->fresh();
        });
    }

    /**
     * Venta manual básica: reutiliza la misma matemática (SaleCalculator).
     * Los totales del frontend se ignoran.
     *
     * @param  array<string, mixed>  $data  Datos validados del FormRequest.
     *
     * @throws ValidationException
     */
    public function createManual(array $data, User $actor): Sale
    {
        return DB::transaction(function () use ($data, $actor) {
            $data['owner_id'] = DataScope::normalizeOwnerId($actor, $data['owner_id'] ?? null);
            $this->assertScope($data, $actor);
            $this->assertCoherence($data);

            $items = $this->normalizeItems($data['items']);
            $calculation = $this->calculator->calculate($items);

            $sale = Sale::create([
                'number' => 'TMP-'.Str::uuid(),
                'company_id' => $data['company_id'],
                'contact_id' => $data['contact_id'] ?? null,
                'opportunity_id' => $data['opportunity_id'] ?? null,
                'owner_id' => $data['owner_id'] ?? null,
                'status' => 'draft',
                'currency' => $data['currency'],
                'sale_date' => $data['sale_date'] ?? today()->toDateString(),
                'subtotal' => $calculation['subtotal'],
                'discount_total' => $calculation['discount_total'],
                'tax_total' => $calculation['tax_total'],
                'total' => $calculation['total'],
                'notes' => $data['notes'] ?? null,
            ]);

            $sale->update([
                'number' => sprintf('S-%s-%06d', now()->format('Y'), $sale->id),
            ]);

            $this->syncItems($sale, $items, $calculation['lines']);
            $this->log($sale, $actor, "Venta {$sale->number} creada");

            return $sale->fresh();
        });
    }

    /**
     * Actualiza una venta manual en borrador (cabecera + reemplazo de líneas).
     * Las ventas con quote_id solo admiten notas (historia de la cotización).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(Sale $sale, array $data, User $actor): Sale
    {
        return DB::transaction(function () use ($sale, $data, $actor) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden editar ventas en borrador.',
                ]);
            }

            if ($sale->quote_id !== null) {
                $sale->update(['notes' => $data['notes'] ?? null]);

                return $sale->fresh();
            }

            $data['owner_id'] = DataScope::normalizeOwnerId($actor, $data['owner_id'] ?? null, $sale->owner_id);
            $this->assertScope($data, $actor, $sale->owner_id);
            $this->assertCoherence($data);

            $items = $this->normalizeItems($data['items']);
            $calculation = $this->calculator->calculate($items);

            $sale->update([
                'company_id' => $data['company_id'],
                'contact_id' => $data['contact_id'] ?? null,
                'opportunity_id' => $data['opportunity_id'] ?? null,
                'owner_id' => $data['owner_id'] ?? null,
                'currency' => $data['currency'],
                'sale_date' => $data['sale_date'] ?? $sale->sale_date,
                'subtotal' => $calculation['subtotal'],
                'discount_total' => $calculation['discount_total'],
                'tax_total' => $calculation['tax_total'],
                'total' => $calculation['total'],
                'notes' => $data['notes'] ?? null,
            ]);

            $sale->items()->delete();
            $this->syncItems($sale, $items, $calculation['lines']);

            return $sale->fresh();
        });
    }

    /**
     * Transiciones: draft→confirmed/cancelled, confirmed→completed/cancelled.
     * No se puede cancelar con factura interna vigente (cancelarla primero).
     *
     * @throws ValidationException
     */
    public function transition(Sale $sale, string $to, User $actor): Sale
    {
        return DB::transaction(function () use ($sale, $to, $actor) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            $allowed = [
                'draft' => ['confirmed', 'cancelled'],
                'confirmed' => ['completed', 'cancelled'],
            ];

            if (! in_array($to, $allowed[$sale->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => "No se puede pasar de {$sale->status} a {$to}.",
                ]);
            }

            if ($to === 'cancelled' && $sale->invoice()->whereNotIn('status', ['cancelled'])->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'La venta tiene factura interna vigente. Cancela la factura primero.',
                ]);
            }

            $sale->update([
                'status' => $to,
                'completed_at' => $to === 'completed' ? now() : null,
                'cancelled_at' => $to === 'cancelled' ? now() : null,
            ]);

            $labels = ['confirmed' => 'confirmada', 'completed' => 'completada', 'cancelled' => 'cancelada'];
            $this->log($sale, $actor, "Venta {$sale->number} {$labels[$to]}");

            return $sale->fresh();
        });
    }

    /**
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
                'sku' => $product?->sku,
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
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, string>>  $calculated
     */
    private function syncItems(Sale $sale, array $items, array $calculated): void
    {
        foreach ($items as $position => $item) {
            $calc = $calculated[$position];

            $sale->items()->create([
                'product_id' => $item['product_id'],
                'position' => $position,
                'sku' => $item['sku'],
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

    private function log(Sale $sale, User $actor, string $subject): void
    {
        $target = $sale->opportunity ?? $sale->company;

        $target?->activities()->create([
            'type' => 'status_change',
            'subject' => $subject,
            'description' => "{$subject} por {$actor->name}. Total: {$sale->total} {$sale->currency}.",
            'status' => 'completed',
            'completed_at' => now(),
            'user_id' => $actor->id,
        ]);
    }
}
