<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\Pipeline;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PipelineSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseEightTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, PipelineSeeder::class]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $user->permissions()->sync(
            Permission::whereIn('name', $permissions)->pluck('id')->all()
        );

        return $user;
    }

    private function saleAccess(): User
    {
        return $this->userWith(['sales.view', 'sales.create', 'sales.update', 'sales.delete']);
    }

    private function invoiceAccess(): User
    {
        return $this->userWith(['invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete']);
    }

    private function fullAccess(): User
    {
        return $this->userWith([
            'sales.view', 'sales.create', 'sales.update', 'sales.delete',
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete',
            'quotes.view', 'quotes.create', 'quotes.update', 'quotes.delete',
            'products.view', 'companies.view', 'contacts.view', 'opportunities.view',
        ]);
    }

    private function itemPayload(array $overrides = []): array
    {
        return array_merge([
            'product_id' => null,
            'description' => 'Servicio X',
            'unit' => 'service',
            'quantity' => 2,
            'unit_price' => '100.00',
            'discount_type' => 'none',
            'discount_value' => 0,
            'tax_rate' => 0,
        ], $overrides);
    }

    private function salePayload(Company $company, array $overrides = [], array $items = []): array
    {
        return array_merge([
            'company_id' => $company->id,
            'currency' => 'USD',
            'sale_date' => now()->format('Y-m-d'),
            'items' => $items ?: [$this->itemPayload()],
        ], $overrides);
    }

    private function quotePayload(Company $company, array $overrides = [], array $items = []): array
    {
        return array_merge([
            'company_id' => $company->id,
            'currency' => 'USD',
            'issue_date' => now()->format('Y-m-d'),
            'valid_until' => now()->addDays(15)->format('Y-m-d'),
            'items' => $items ?: [$this->itemPayload()],
        ], $overrides);
    }

    private function acceptedQuote(User $user, Company $company, array $items = []): Quote
    {
        $quotes = app(\App\Services\Quotes\QuoteService::class);
        $quote = $quotes->create($this->quotePayload($company, ['owner_id' => $user->id], $items), $user);

        return $quotes->transition($quote, 'accepted', $user);
    }

    private function convertQuote(User $user, Quote $quote, array $overrides = []): Sale
    {
        $sales = app(\App\Services\Sales\SaleCreationService::class);

        return $sales->fromQuote($quote, $user, $overrides);
    }

    // ---------------- Ventas CRUD ----------------

    public function test_authorized_user_sees_sales_list(): void
    {
        $user = $this->saleAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);
        Sale::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id, 'number' => 'S-2026-000001']);

        $this->actingAs($user)->get('/sales')->assertOk()->assertSee('S-2026-000001');
    }

    public function test_user_without_permission_cannot_access_sales(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, PipelineSeeder::class]);
        $user = User::factory()->create(['status' => 'active']);
        $sale = Sale::factory()->create();

        $this->actingAs($user)->get('/sales')->assertForbidden();
        $this->actingAs($user)->get('/sales/create')->assertForbidden();
        $this->actingAs($user)->post('/sales', [])->assertForbidden();
        $this->actingAs($user)->get("/sales/{$sale->id}")->assertForbidden();
        $this->actingAs($user)->get("/sales/{$sale->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/sales/{$sale->id}", [])->assertForbidden();
        $this->actingAs($user)->delete("/sales/{$sale->id}")->assertForbidden();
        $this->actingAs($user)->patch("/sales/{$sale->id}/confirm")->assertForbidden();
        $this->actingAs($user)->patch("/sales/{$sale->id}/complete")->assertForbidden();
        $this->actingAs($user)->patch("/sales/{$sale->id}/cancel")->assertForbidden();
        $this->actingAs($user)->get("/sales/{$sale->id}/print")->assertForbidden();
    }

    public function test_create_manual_sale_recalculates_totals(): void
    {
        $user = $this->saleAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        // Totales falsos enviados: el backend recalcula (2×100=200, -10% →180, +18% →212.40).
        $response = $this->actingAs($user)->post('/sales', $this->salePayload($company, [
            'owner_id' => $user->id,
            'subtotal' => '1.00', 'discount_total' => '1.00', 'tax_total' => '1.00', 'total' => '1.00',
        ], [$this->itemPayload(['discount_type' => 'percentage', 'discount_value' => 10, 'tax_rate' => 18])]));

        $sale = Sale::firstOrFail();
        $response->assertRedirect(route('sales.show', $sale));
        $this->assertMatchesRegularExpression('/^S-\d{4}-\d{6}$/', $sale->number);
        $this->assertSame('draft', $sale->status);
        $this->assertEquals('200.00', $sale->subtotal);
        $this->assertEquals('20.00', $sale->discount_total);
        $this->assertEquals('32.40', $sale->tax_total);
        $this->assertEquals('212.40', $sale->total);
    }

    public function test_create_sale_validation(): void
    {
        $user = $this->saleAccess();

        $response = $this->actingAs($user)->from('/sales/create')->post('/sales', [
            'company_id' => '',
            'items' => [$this->itemPayload(['quantity' => 0])],
        ]);

        $response->assertRedirect('/sales/create');
        $response->assertSessionHasErrors(['company_id', 'items.0.quantity']);
    }

    public function test_update_manual_draft_and_quote_sourced_notes_only(): void
    {
        $user = $this->fullAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        // Manual: edición completa.
        $this->actingAs($user)->post('/sales', $this->salePayload($company, ['owner_id' => $user->id]))->assertRedirect();
        $sale = Sale::firstOrFail();
        $this->actingAs($user)->put("/sales/{$sale->id}", $this->salePayload($company, [
            'notes' => 'Actualizada',
        ], [$this->itemPayload(['quantity' => 5, 'unit_price' => '10.00'])]))->assertRedirect();
        $sale = $sale->fresh();
        $this->assertSame('Actualizada', $sale->notes);
        $this->assertEquals('50.00', $sale->total);

        // Con cotización: solo notas.
        $quote = $this->acceptedQuote($user, $company);
        $sold = $this->convertQuote($user, $quote);
        $itemsBefore = $sold->items()->count();
        $this->actingAs($user)->put("/sales/{$sold->id}", ['notes' => 'Solo notas'])->assertRedirect();
        $sold = $sold->fresh();
        $this->assertSame('Solo notas', $sold->notes);
        $this->assertSame($itemsBefore, $sold->items()->count());

        // No-draft: 403.
        $sold->update(['status' => 'confirmed']);
        $this->actingAs($user)->put("/sales/{$sold->id}", ['notes' => 'X'])->assertForbidden();
    }

    public function test_delete_sale_soft_deletes_and_keeps_items(): void
    {
        $user = $this->saleAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->post('/sales', $this->salePayload($company))->assertRedirect();
        $sale = Sale::firstOrFail();

        $this->actingAs($user)->delete("/sales/{$sale->id}")->assertRedirect(route('sales.index'));
        $this->assertSoftDeleted('sales', ['id' => $sale->id]);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $sale->id]);
    }

    public function test_search_filter_sort_paginate_sales(): void
    {
        $user = $this->saleAccess();
        $company = Company::factory()->create(['trade_name' => 'BuscadaV S.A.S.', 'owner_id' => $user->id]);

        $this->actingAs($user)->post('/sales', $this->salePayload($company, ['owner_id' => $user->id]))->assertRedirect();
        $mine = Sale::firstOrFail()->number;

        $other = Company::factory()->create(['trade_name' => 'Otra S.A.S.', 'owner_id' => $user->id]);
        $this->actingAs($user)->post('/sales', $this->salePayload($other, ['owner_id' => $user->id]))->assertRedirect();

        $response = $this->actingAs($user)->get('/sales?search=buscadav');
        $response->assertOk()->assertSee($mine);

        $response = $this->actingAs($user)->get(
            "/sales?status=draft&company_id={$company->id}&owner_id={$user->id}&currency=USD"
            .'&sold_from='.now()->format('Y-m-d').'&sold_to='.now()->addDay()->format('Y-m-d')
        );
        $response->assertOk()->assertSee($mine);

        $response = $this->actingAs($user)->get('/sales?sort=total&direction=desc');
        $response->assertOk();

        Sale::query()->delete();
        for ($i = 0; $i < 16; $i++) {
            Sale::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id]);
        }
        $this->assertCount(15, $this->actingAs($user)->get('/sales')->viewData('sales')->items());
        $this->assertCount(1, $this->actingAs($user)->get('/sales?page=2')->viewData('sales')->items());
    }

    public function test_sale_transitions_and_timestamps(): void
    {
        $user = $this->saleAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->post('/sales', $this->salePayload($company))->assertRedirect();
        $sale = Sale::firstOrFail();

        $this->actingAs($user)->patch("/sales/{$sale->id}/confirm")->assertRedirect();
        $this->assertSame('confirmed', $sale->fresh()->status);

        $this->actingAs($user)->patch("/sales/{$sale->id}/complete")->assertRedirect();
        $sale = $sale->fresh();
        $this->assertSame('completed', $sale->status);
        $this->assertNotNull($sale->completed_at);

        // Terminal: no se puede cancelar una completada.
        $this->actingAs($user)->from("/sales/{$sale->id}")
            ->patch("/sales/{$sale->id}/cancel")->assertSessionHasErrors('status');
        $this->assertSame('completed', $sale->fresh()->status);

        // Cancelación directa desde borrador.
        $this->actingAs($user)->post('/sales', $this->salePayload($company))->assertRedirect();
        $sale2 = Sale::latest('id')->firstOrFail();
        $this->actingAs($user)->patch("/sales/{$sale2->id}/cancel")->assertRedirect();
        $sale2 = $sale2->fresh();
        $this->assertSame('cancelled', $sale2->status);
        $this->assertNotNull($sale2->cancelled_at);
    }

    // ---------------- Quote → Sale ----------------

    public function test_accepted_quote_converts_to_sale_with_exact_totals(): void
    {
        $user = $this->fullAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);
        $quote = $this->acceptedQuote($user, $company, [
            $this->itemPayload(['quantity' => 2, 'unit_price' => '100.00', 'discount_type' => 'percentage', 'discount_value' => 10, 'tax_rate' => 18]),
        ]);

        $response = $this->actingAs($user)->post("/quotes/{$quote->id}/sale", []);

        $sale = Sale::firstOrFail();
        $response->assertRedirect(route('sales.show', $sale));
        $this->assertMatchesRegularExpression('/^S-\d{4}-\d{6}$/', $sale->number);
        $this->assertSame($quote->id, $sale->quote_id);
        $this->assertSame($company->id, $sale->company_id);
        $this->assertEquals($quote->subtotal, $sale->subtotal);
        $this->assertEquals($quote->discount_total, $sale->discount_total);
        $this->assertEquals($quote->tax_total, $sale->tax_total);
        $this->assertEquals($quote->total, $sale->total);
        $this->assertSame($quote->items()->count(), $sale->items()->count());

        $quoteItem = $quote->items()->first();
        $saleItem = $sale->items()->first();
        foreach (['description', 'quantity', 'unit_price', 'discount_type', 'discount_value', 'tax_rate', 'subtotal', 'discount_amount', 'tax_amount', 'total'] as $field) {
            $this->assertEquals((string) $quoteItem->$field, (string) $saleItem->$field, "Campo {$field} difiere");
        }

        // Actividad registrada.
        $this->assertTrue($company->activities()->where('subject', 'like', 'Venta S-%')->exists());

        // La ficha de la cotización enlaza la venta y ya no ofrece convertir.
        $this->actingAs($user)->get("/quotes/{$quote->id}")
            ->assertOk()->assertSee($sale->number)->assertDontSee('Crear venta');
    }

    public function test_non_accepted_quote_cannot_convert(): void
    {
        $user = $this->fullAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);
        $quotes = app(\App\Services\Quotes\QuoteService::class);
        $quote = $quotes->create($this->quotePayload($company, ['owner_id' => $user->id]), $user);

        $this->actingAs($user)->get("/quotes/{$quote->id}/sale/create")->assertRedirect();
        $this->actingAs($user)->from("/quotes/{$quote->id}")
            ->post("/quotes/{$quote->id}/sale", [])->assertSessionHasErrors('quote');
        $this->assertSame(0, Sale::count());
    }

    public function test_double_conversion_rejected_without_duplicates(): void
    {
        $user = $this->fullAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);
        $quote = $this->acceptedQuote($user, $company);

        $this->actingAs($user)->post("/quotes/{$quote->id}/sale", [])->assertRedirect();
        $this->assertSame(1, Sale::count());
        $items = SaleItem::count();

        $response = $this->actingAs($user)->from("/quotes/{$quote->id}")
            ->post("/quotes/{$quote->id}/sale", []);

        $response->assertRedirect("/quotes/{$quote->id}");
        $response->assertSessionHasErrors('quote');
        $this->assertSame(1, Sale::count());
        $this->assertSame($items, SaleItem::count());
    }

    public function test_sale_snapshot_survives_product_and_quote_changes(): void
    {
        $user = $this->fullAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);
        $product = Product::factory()->create(['name' => 'Original', 'price' => '100.00', 'tax_rate' => '10.00']);

        $quotes = app(\App\Services\Quotes\QuoteService::class);
        $quote = $quotes->create($this->quotePayload($company, ['owner_id' => $user->id], [
            $this->itemPayload(['product_id' => $product->id, 'description' => null, 'unit_price' => null, 'tax_rate' => null]),
        ]), $user);
        $quote = $quotes->transition($quote, 'accepted', $user);
        $sale = $this->convertQuote($user, $quote);

        $product->update(['name' => 'Cambiado', 'price' => '999.00', 'tax_rate' => '50.00']);
        $quote->items()->update(['unit_price' => '1.00', 'description' => 'Alterado']);

        $item = $sale->items()->firstOrFail();
        $this->assertSame('Original', $item->description);
        $this->assertEquals('100.00', $item->unit_price);
        $this->assertEquals('10.00', $item->tax_rate);
    }

    // ---------------- Facturas ----------------

    public function test_user_without_permission_cannot_access_invoices(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, PipelineSeeder::class]);
        $user = User::factory()->create(['status' => 'active']);
        $invoice = Invoice::factory()->create();

        $this->actingAs($user)->get('/invoices')->assertForbidden();
        $this->actingAs($user)->get("/invoices/{$invoice->id}")->assertForbidden();
        $this->actingAs($user)->delete("/invoices/{$invoice->id}")->assertForbidden();
        $this->actingAs($user)->get("/invoices/{$invoice->id}/print")->assertForbidden();
        $this->actingAs($user)->patch("/invoices/{$invoice->id}/send")->assertForbidden();
        $this->actingAs($user)->patch("/invoices/{$invoice->id}/pay")->assertForbidden();
        $this->actingAs($user)->patch("/invoices/{$invoice->id}/cancel")->assertForbidden();
    }

    public function test_invoice_created_from_confirmed_sale_copies_snapshots(): void
    {
        $user = $this->fullAccess();
        $company = Company::factory()->create([
            'trade_name' => 'Snapshot S.A.S.', 'tax_id' => '900111222-3',
            'address' => 'Calle 1', 'city' => 'Bogotá', 'owner_id' => $user->id,
        ]);
        $contact = Contact::factory()->create([
            'first_name' => 'Fac', 'last_name' => 'Turado', 'email' => 'fac@example.com',
            'company_id' => $company->id, 'owner_id' => $user->id,
        ]);
        $quote = $this->acceptedQuote($user, $company, [
            $this->itemPayload(['quantity' => 2, 'unit_price' => '100.00']),
        ]);
        $sale = $this->convertQuote($user, $quote);
        $sale->update(['contact_id' => $contact->id]);
        $sales = app(\App\Services\Sales\SaleCreationService::class);
        $sale = $sales->transition($sale, 'confirmed', $user);

        $response = $this->actingAs($user)->post("/sales/{$sale->id}/invoice", [
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = Invoice::firstOrFail();
        $response->assertRedirect(route('invoices.show', $invoice));
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{6}$/', $invoice->number);
        $this->assertSame($sale->id, $invoice->sale_id);
        $this->assertEquals($sale->total, $invoice->total);
        $this->assertEquals($sale->subtotal, $invoice->subtotal);
        $this->assertSame($sale->items()->count(), $invoice->items()->count());

        $saleItem = $sale->items()->first();
        $invoiceItem = $invoice->items()->first();
        $this->assertSame($saleItem->id, $invoiceItem->sale_item_id);
        foreach (['description', 'quantity', 'unit_price', 'tax_rate', 'total'] as $field) {
            $this->assertEquals((string) $saleItem->$field, (string) $invoiceItem->$field, "Campo {$field} difiere");
        }

        // Snapshot de empresa/contacto.
        $this->assertSame('Snapshot S.A.S.', $invoice->company_name);
        $this->assertSame('900111222-3', $invoice->company_tax_id);
        $this->assertSame('Fac Turado', $invoice->contact_name);
        $this->assertSame('fac@example.com', $invoice->contact_email);
    }

    public function test_draft_or_cancelled_sale_cannot_be_invoiced(): void
    {
        $user = $this->fullAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->post('/sales', $this->salePayload($company))->assertRedirect();
        $sale = Sale::firstOrFail();

        $this->actingAs($user)->get("/sales/{$sale->id}/invoice/create")->assertRedirect();
        $this->actingAs($user)->from("/sales/{$sale->id}")
            ->post("/sales/{$sale->id}/invoice", [])->assertSessionHasErrors('sale');
        $this->assertSame(0, Invoice::count());
    }

    public function test_double_invoicing_rejected(): void
    {
        $user = $this->fullAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);
        $quote = $this->acceptedQuote($user, $company);
        $sale = $this->convertQuote($user, $quote);
        $sales = app(\App\Services\Sales\SaleCreationService::class);
        $sale = $sales->transition($sale, 'confirmed', $user);

        $this->actingAs($user)->post("/sales/{$sale->id}/invoice", [])->assertRedirect();
        $this->assertSame(1, Invoice::count());
        $items = \App\Models\InvoiceItem::count();

        $response = $this->actingAs($user)->from("/sales/{$sale->id}")
            ->post("/sales/{$sale->id}/invoice", []);

        $response->assertRedirect("/sales/{$sale->id}");
        $response->assertSessionHasErrors('sale');
        $this->assertSame(1, Invoice::count());
        $this->assertSame($items, \App\Models\InvoiceItem::count());
    }

    public function test_invoice_snapshot_survives_later_changes(): void
    {
        $user = $this->fullAccess();
        $company = Company::factory()->create(['trade_name' => 'Antes S.A.S.', 'tax_id' => '111', 'owner_id' => $user->id]);
        $product = Product::factory()->create(['name' => 'Orig', 'price' => '50.00', 'tax_rate' => '5.00']);
        $quote = $this->acceptedQuote($user, $company, [
            $this->itemPayload(['product_id' => $product->id, 'description' => null, 'unit_price' => null, 'tax_rate' => null]),
        ]);
        $sale = $this->convertQuote($user, $quote);
        $sales = app(\App\Services\Sales\SaleCreationService::class);
        $sale = $sales->transition($sale, 'confirmed', $user);
        $this->actingAs($user)->post("/sales/{$sale->id}/invoice", [])->assertRedirect();
        $invoice = Invoice::firstOrFail();

        $company->update(['trade_name' => 'Después S.A.S.', 'tax_id' => '999']);
        $product->update(['name' => 'Cambiado', 'price' => '1.00']);
        $sale->items()->update(['description' => 'Alterado', 'unit_price' => '1.00']);

        $invoice = $invoice->fresh();
        $this->assertSame('Antes S.A.S.', $invoice->company_name);
        $this->assertSame('111', $invoice->company_tax_id);
        $item = $invoice->items()->firstOrFail();
        $this->assertSame('Orig', $item->description);
        $this->assertEquals('50.00', $item->unit_price);
    }

    public function test_invoice_transitions_and_overdue(): void
    {
        $user = $this->invoiceAccess();
        $invoice = Invoice::factory()->create(['status' => 'draft', 'owner_id' => $user->id]);

        $this->actingAs($user)->patch("/invoices/{$invoice->id}/send")->assertRedirect();
        $this->assertSame('sent', $invoice->fresh()->status);

        $this->actingAs($user)->patch("/invoices/{$invoice->id}/pay")->assertRedirect();
        $invoice = $invoice->fresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);

        // paid terminal: no se puede cancelar.
        $this->actingAs($user)->from("/invoices/{$invoice->id}")
            ->patch("/invoices/{$invoice->id}/cancel")->assertSessionHasErrors('status');
        $this->assertSame('paid', $invoice->fresh()->status);

        // overdue efectivo por fecha.
        $overdue = Invoice::factory()->create(['status' => 'sent', 'due_date' => now()->subDay()->format('Y-m-d'), 'owner_id' => $user->id]);
        $this->assertTrue($overdue->is_overdue);
        $paid = Invoice::factory()->create(['status' => 'paid', 'due_date' => now()->subDay()->format('Y-m-d'), 'owner_id' => $user->id]);
        $this->assertFalse($paid->is_overdue);

        // draft/sent → cancelled.
        $draft = Invoice::factory()->create(['status' => 'draft', 'owner_id' => $user->id]);
        $this->actingAs($user)->patch("/invoices/{$draft->id}/cancel")->assertRedirect();
        $this->assertSame('cancelled', $draft->fresh()->status);
    }

    public function test_invoice_soft_delete_and_print(): void
    {
        $user = $this->invoiceAccess();
        $invoice = Invoice::factory()->create(['owner_id' => $user->id]);
        \App\Models\InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $this->actingAs($user)->get("/invoices/{$invoice->id}/print")
            ->assertOk()->assertSee('Sin validez fiscal');
        $this->actingAs($user)->delete("/invoices/{$invoice->id}")
            ->assertRedirect(route('invoices.index'));
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $invoice->id]);
    }

    public function test_search_filter_sort_paginate_invoices(): void
    {
        $user = $this->invoiceAccess();
        $company = Company::factory()->create(['trade_name' => 'BuscadaF S.A.S.', 'owner_id' => $user->id]);
        Invoice::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id, 'status' => 'draft']);
        $mine = Invoice::firstOrFail()->number;
        Invoice::factory()->create(['status' => 'paid']);

        $response = $this->actingAs($user)->get('/invoices?search=buscadaf');
        $response->assertOk()->assertSee($mine);

        $response = $this->actingAs($user)->get(
            "/invoices?status=draft&company_id={$company->id}&owner_id={$user->id}&currency=USD"
            .'&issued_from='.now()->format('Y-m-d').'&issued_to='.now()->addDay()->format('Y-m-d')
        );
        $response->assertOk()->assertSee($mine);

        $response = $this->actingAs($user)->get('/invoices?sort=total&direction=desc');
        $response->assertOk();

        Invoice::query()->delete();
        for ($i = 0; $i < 16; $i++) {
            Invoice::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id]);
        }
        $this->assertCount(15, $this->actingAs($user)->get('/invoices')->viewData('invoices')->items());
        $this->assertCount(1, $this->actingAs($user)->get('/invoices?page=2')->viewData('invoices')->items());
    }

    public function test_sale_cancel_blocked_with_active_invoice(): void
    {
        $user = $this->fullAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);
        $quote = $this->acceptedQuote($user, $company);
        $sale = $this->convertQuote($user, $quote);
        $sales = app(\App\Services\Sales\SaleCreationService::class);
        $sale = $sales->transition($sale, 'confirmed', $user);
        $this->actingAs($user)->post("/sales/{$sale->id}/invoice", [])->assertRedirect();

        $this->actingAs($user)->from("/sales/{$sale->id}")
            ->patch("/sales/{$sale->id}/cancel")->assertSessionHasErrors('status');
        $this->assertSame('confirmed', $sale->fresh()->status);

        // Tras cancelar la factura, la venta sí puede cancelarse.
        $invoice = Invoice::firstOrFail();
        $invoices = app(\App\Services\Sales\InvoiceCreationService::class);
        $invoices->transition($invoice, 'cancelled', $user);
        $this->actingAs($user)->patch("/sales/{$sale->id}/cancel")->assertRedirect();
        $this->assertSame('cancelled', $sale->fresh()->status);
    }

    public function test_integrations_and_dashboard(): void
    {
        $user = $this->fullAccess();
        $company = Company::factory()->create(['trade_name' => 'Integra8 S.A.S.', 'owner_id' => $user->id]);
        $contact = Contact::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id]);
        $quote = $this->acceptedQuote($user, $company);
        $sale = $this->convertQuote($user, $quote);
        $sale->update(['contact_id' => $contact->id]);
        $sales = app(\App\Services\Sales\SaleCreationService::class);
        $sale = $sales->transition($sale, 'confirmed', $user);
        $this->actingAs($user)->post("/sales/{$sale->id}/invoice", [
            'due_date' => now()->addDays(2)->format('Y-m-d'),
        ])->assertRedirect();
        $invoice = Invoice::firstOrFail();

        $this->actingAs($user)->get("/quotes/{$quote->id}")->assertOk()->assertSee($sale->number);
        $this->actingAs($user)->get("/companies/{$company->id}")->assertOk()
            ->assertSee($sale->number)->assertSee($invoice->number);
        $this->actingAs($user)->get("/contacts/{$contact->id}")->assertOk()->assertSee($invoice->number);
        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee($sale->number)->assertSee($invoice->number);
        $this->actingAs($user)->get("/sales/{$sale->id}/print")->assertOk()->assertSee($sale->number);
    }
}
