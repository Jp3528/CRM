<?php

namespace App\Services\Sales;

use App\Models\Invoice;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceCreationService
{
    /**
     * Genera la factura interna desde una venta (1 Sale → máximo 1 Invoice).
     * Copia snapshots del SaleItem (nunca del catálogo) y los totales exactos.
     * Todo transaccional con lock anti-races.
     *
     * @param  array<string, mixed>  $data  validated: due_date?, notes?
     *
     * @throws ValidationException
     */
    public function fromSale(Sale $sale, User $actor, array $data = []): Invoice
    {
        return DB::transaction(function () use ($sale, $actor, $data) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->trashed()) {
                throw ValidationException::withMessages([
                    'sale' => 'No se puede facturar una venta eliminada.',
                ]);
            }

            if (! in_array($sale->status, ['confirmed', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'sale' => 'Solo se pueden facturar ventas confirmadas o completadas.',
                ]);
            }

            if ($sale->invoice()->exists()) {
                throw ValidationException::withMessages([
                    'sale' => 'Esta venta ya generó una factura.',
                ]);
            }

            $sale->loadMissing(['company', 'contact']);

            $invoice = Invoice::create([
                'number' => 'TMP-'.Str::uuid(),
                'sale_id' => $sale->id,
                'company_id' => $sale->company_id,
                'contact_id' => $sale->contact_id,
                'owner_id' => $sale->owner_id,
                'status' => 'draft',
                'currency' => $sale->currency,
                'issue_date' => today()->toDateString(),
                'due_date' => $data['due_date'] ?? today()->addDays(30)->toDateString(),
                'subtotal' => $sale->subtotal,
                'discount_total' => $sale->discount_total,
                'tax_total' => $sale->tax_total,
                'total' => $sale->total,
                'notes' => $data['notes'] ?? null,
                // Snapshot de la empresa/contacto al facturar.
                'company_name' => $sale->company?->trade_name,
                'company_tax_id' => $sale->company?->tax_id,
                'company_address' => collect([
                    $sale->company?->address, $sale->company?->city,
                    $sale->company?->region, $sale->company?->country,
                ])->filter()->join(', ') ?: null,
                'contact_name' => $sale->contact
                    ? trim("{$sale->contact->first_name} {$sale->contact->last_name}")
                    : null,
                'contact_email' => $sale->contact?->email,
            ]);

            $invoice->update([
                'number' => sprintf('INV-%s-%06d', now()->format('Y'), $invoice->id),
            ]);

            foreach ($sale->items()->orderBy('position')->get() as $position => $item) {
                $invoice->items()->create([
                    'sale_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'position' => $position,
                    'sku' => $item->sku,
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

            $sale->company?->activities()->create([
                'type' => 'status_change',
                'subject' => "Factura {$invoice->number} generada",
                'description' => "Generada desde venta {$sale->number} por {$actor->name}. Total: {$invoice->total} {$invoice->currency}. Documento interno, no fiscal.",
                'status' => 'completed',
                'completed_at' => now(),
                'user_id' => $actor->id,
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * Transiciones: draft→sent/paid/cancelled, sent→paid/cancelled.
     * paid es terminal (sin nota de crédito en esta fase). Registro manual
     * interno: paid_at solo marca, sin transacción de pago real.
     *
     * @throws ValidationException
     */
    public function transition(Invoice $invoice, string $to, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $to, $actor) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $allowed = [
                'draft' => ['sent', 'paid', 'cancelled'],
                'sent' => ['paid', 'cancelled'],
            ];

            if (! in_array($to, $allowed[$invoice->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => "No se puede pasar de {$invoice->status} a {$to}.",
                ]);
            }

            $invoice->update([
                'status' => $to,
                'paid_at' => $to === 'paid' ? now() : null,
                'cancelled_at' => $to === 'cancelled' ? now() : null,
            ]);

            $labels = ['sent' => 'enviada', 'paid' => 'pagada', 'cancelled' => 'cancelada'];
            $invoice->company?->activities()->create([
                'type' => 'status_change',
                'subject' => "Factura {$invoice->number} {$labels[$to]}",
                'description' => "Cambio de estado por {$actor->name}. Registro administrativo interno.",
                'status' => 'completed',
                'completed_at' => now(),
                'user_id' => $actor->id,
            ]);

            return $invoice->fresh();
        });
    }
}
