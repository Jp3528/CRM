<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\Pipeline;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PipelineSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseSevenTest extends TestCase
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

    private function productAccess(): User
    {
        return $this->userWith(['products.view', 'products.create', 'products.update', 'products.delete']);
    }

    private function quoteAccess(): User
    {
        return $this->userWith(['quotes.view', 'quotes.create', 'quotes.update', 'quotes.delete']);
    }

    private function fullSalesAccess(): User
    {
        return $this->userWith([
            'products.view', 'products.create', 'products.update', 'products.delete',
            'quotes.view', 'quotes.create', 'quotes.update', 'quotes.delete',
            'companies.view', 'contacts.view', 'opportunities.view',
        ]);
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::factory()->create($overrides);
    }

    private function productPayload(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'TEST-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Producto Test',
            'description' => 'Descripción.',
            'unit' => 'unit',
            'price' => '150000.00',
            'cost' => '90000.00',
            'tax_rate' => '19.00',
            'status' => 'active',
        ], $overrides);
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

    // ---------------- Productos ----------------

    public function test_authorized_user_sees_products_list(): void
    {
        $user = $this->productAccess();
        $this->makeProduct(['name' => 'Listado Visible', 'created_by' => $user->id]);

        $this->actingAs($user)->get('/products')->assertOk()->assertSee('Listado Visible');
    }

    public function test_user_without_permission_cannot_access_products(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, PipelineSeeder::class]);
        $user = User::factory()->create(['status' => 'active']);
        $product = $this->makeProduct();

        $this->actingAs($user)->get('/products')->assertForbidden();
        $this->actingAs($user)->get('/products/create')->assertForbidden();
        $this->actingAs($user)->post('/products', $this->productPayload())->assertForbidden();
        $this->actingAs($user)->get("/products/{$product->id}")->assertForbidden();
        $this->actingAs($user)->get("/products/{$product->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/products/{$product->id}", $this->productPayload())->assertForbidden();
        $this->actingAs($user)->delete("/products/{$product->id}")->assertForbidden();
    }

    public function test_create_product_normalizes_sku(): void
    {
        $user = $this->productAccess();

        $response = $this->actingAs($user)->post('/products', $this->productPayload(['sku' => '  mixto-01 ']));

        $product = Product::where('sku', 'MIXTO-01')->firstOrFail();
        $response->assertRedirect(route('products.show', $product));
        $response->assertSessionHas('success', 'Producto creado correctamente.');
    }

    public function test_product_sku_unique_and_validation(): void
    {
        $user = $this->productAccess();
        $this->makeProduct(['sku' => 'DUPLI-01']);

        $response = $this->actingAs($user)->from('/products/create')->post('/products', $this->productPayload([
            'sku' => 'DUPLI-01',
            'name' => '',
            'price' => -5,
            'tax_rate' => 150,
            'status' => 'raro',
        ]));

        $response->assertRedirect('/products/create');
        $response->assertSessionHasErrors(['sku', 'name', 'price', 'tax_rate', 'status']);
    }

    public function test_product_cost_visible_only_to_editors(): void
    {
        $viewer = $this->userWith(['products.view']);
        $editor = $this->productAccess();
        $product = $this->makeProduct(['cost' => '12345.00']);

        $this->actingAs($viewer)->get("/products/{$product->id}")
            ->assertOk()->assertDontSee('12,345.00');
        $this->actingAs($editor)->get("/products/{$product->id}")
            ->assertOk()->assertSee('12,345.00');
    }

    public function test_update_and_soft_delete_product_keeps_history(): void
    {
        $user = $this->fullSalesAccess();
        $product = $this->makeProduct(['price' => '100.00', 'tax_rate' => '0.00']);
        $company = Company::factory()->create(['owner_id' => $user->id]);

        // Cotización que usa el producto.
        $this->actingAs($user)->post('/quotes', $this->quotePayload($company, [], [
            $this->itemPayload(['product_id' => $product->id, 'description' => null, 'unit_price' => null, 'tax_rate' => null]),
        ]))->assertRedirect();
        $itemId = QuoteItem::firstOrFail()->id;

        $this->actingAs($user)->put("/products/{$product->id}", $this->productPayload([
            'sku' => $product->sku, 'price' => '999.00',
        ]))->assertRedirect();
        $this->assertEquals('999.00', $product->fresh()->price);

        $this->actingAs($user)->delete("/products/{$product->id}")->assertRedirect();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        // Histórico intacto, con product_id en null (nullOnDelete) pero valores congelados.
        $this->assertDatabaseHas('quote_items', ['id' => $itemId, 'unit_price' => '100.00']);
    }

    public function test_search_filter_sort_paginate_products(): void
    {
        $user = $this->productAccess();
        $cat = ProductCategory::factory()->create(['name' => 'FiltroCat']);
        $this->makeProduct(['sku' => 'BUSCADO-1', 'name' => 'BuscadoXYZ', 'status' => 'active', 'category_id' => $cat->id, 'unit' => 'hour', 'price' => '10.00']);
        $this->makeProduct(['name' => 'Otro', 'status' => 'inactive', 'price' => '9999.00']);

        $response = $this->actingAs($user)->get('/products?search=buscadoxYZ');
        $response->assertOk();
        $response->assertSee('BuscadoXYZ');
        $response->assertDontSee('>Otro<');

        $response = $this->actingAs($user)->get("/products?status=active&category_id={$cat->id}&unit=hour");
        $response->assertSee('BuscadoXYZ');

        $response = $this->actingAs($user)->get('/products?sort=price&direction=desc');
        $items = $response->viewData('products')->items();
        $this->assertGreaterThanOrEqual((float) $items[1]->price, (float) $items[0]->price);

        Product::query()->delete();
        Product::factory(16)->create();
        $this->assertCount(15, $this->actingAs($user)->get('/products')->viewData('products')->items());
        $this->assertCount(1, $this->actingAs($user)->get('/products?page=2')->viewData('products')->items());
    }

    public function test_inactive_product_rejected_in_new_lines(): void
    {
        $user = $this->fullSalesAccess();
        $product = $this->makeProduct(['status' => 'inactive']);
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)->from('/quotes/create')->post('/quotes', $this->quotePayload($company, [], [
            $this->itemPayload(['product_id' => $product->id, 'description' => null, 'unit_price' => null, 'tax_rate' => null]),
        ]));

        $response->assertRedirect('/quotes/create');
        $response->assertSessionHasErrors('items.0.product_id');
        $this->assertSame(0, Quote::count());
    }

    // ---------------- Cotizaciones CRUD ----------------

    public function test_user_without_permission_cannot_access_quotes(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, PipelineSeeder::class]);
        $user = User::factory()->create(['status' => 'active']);
        $quote = Quote::factory()->create();

        $this->actingAs($user)->get('/quotes')->assertForbidden();
        $this->actingAs($user)->get('/quotes/create')->assertForbidden();
        $this->actingAs($user)->post('/quotes', [])->assertForbidden();
        $this->actingAs($user)->get("/quotes/{$quote->id}")->assertForbidden();
        $this->actingAs($user)->get("/quotes/{$quote->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/quotes/{$quote->id}", [])->assertForbidden();
        $this->actingAs($user)->delete("/quotes/{$quote->id}")->assertForbidden();
        $this->actingAs($user)->patch("/quotes/{$quote->id}/send")->assertForbidden();
        $this->actingAs($user)->patch("/quotes/{$quote->id}/accept")->assertForbidden();
        $this->actingAs($user)->patch("/quotes/{$quote->id}/reject")->assertForbidden();
        $this->actingAs($user)->get("/quotes/{$quote->id}/print")->assertForbidden();
    }

    public function test_create_quote_with_product_and_manual_lines(): void
    {
        $user = $this->fullSalesAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);
        $product = $this->makeProduct(['name' => 'Hora Consultoría', 'price' => '100.00', 'tax_rate' => '10.00', 'unit' => 'hour']);

        $response = $this->actingAs($user)->post('/quotes', $this->quotePayload($company, ['owner_id' => $user->id], [
            $this->itemPayload(['product_id' => $product->id, 'description' => null, 'unit' => null, 'unit_price' => null, 'tax_rate' => null, 'quantity' => 2, 'discount_type' => 'percentage', 'discount_value' => 10]),
            $this->itemPayload(['description' => 'Viáticos', 'quantity' => 1, 'unit_price' => '50.00', 'tax_rate' => 0]),
        ]));

        $quote = Quote::firstOrFail();
        $response->assertRedirect(route('quotes.show', $quote));
        // Número legible Q-AAAA-NNNNNN.
        $this->assertMatchesRegularExpression('/^Q-\d{4}-\d{6}$/', $quote->number);
        $this->assertSame('draft', $quote->status);

        // Línea 1: 2×100=200, -10% (20) =180, +10% (18) =198.
        // Línea 2: 1×50=50 sin descuento ni impuesto.
        $this->assertEquals('250.00', $quote->subtotal);
        $this->assertEquals('20.00', $quote->discount_total);
        $this->assertEquals('18.00', $quote->tax_total);
        $this->assertEquals('248.00', $quote->total);

        $line1 = $quote->items()->orderBy('position')->first();
        $this->assertSame($product->id, $line1->product_id);
        $this->assertSame('Hora Consultoría', $line1->description);
        $this->assertSame('hour', $line1->unit);
        $this->assertEquals('198.00', $line1->total);
    }

    public function test_create_quote_validation(): void
    {
        $user = $this->quoteAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)->from('/quotes/create')->post('/quotes', $this->quotePayload($company, [
            'company_id' => '',
            'valid_until' => now()->subMonth()->format('Y-m-d'),
        ], [$this->itemPayload(['quantity' => 0])]));

        $response->assertRedirect('/quotes/create');
        $response->assertSessionHasErrors(['company_id', 'items.0.quantity', 'valid_until']);
    }

    public function test_backend_ignores_tampered_totals(): void
    {
        $user = $this->fullSalesAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        // Atacante envía totales falsos: el backend debe guardar los calculados.
        $payload = $this->quotePayload($company, [
            'subtotal' => '1.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'total' => '1.00',
        ], [$this->itemPayload(['quantity' => 2, 'unit_price' => '100.00', 'discount_type' => 'percentage', 'discount_value' => 10, 'tax_rate' => 18])]);

        $this->actingAs($user)->post('/quotes', $payload)->assertRedirect();

        $quote = Quote::firstOrFail();
        $this->assertEquals('200.00', $quote->subtotal);
        $this->assertEquals('20.00', $quote->discount_total);
        $this->assertEquals('32.40', $quote->tax_total);
        $this->assertEquals('212.40', $quote->total);
    }

    public function test_snapshot_preserved_when_product_changes(): void
    {
        $user = $this->fullSalesAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);
        $product = $this->makeProduct(['name' => 'Original', 'price' => '100.00', 'tax_rate' => '10.00']);

        $this->actingAs($user)->post('/quotes', $this->quotePayload($company, [], [
            $this->itemPayload(['product_id' => $product->id, 'description' => null, 'unit_price' => null, 'tax_rate' => null]),
        ]))->assertRedirect();

        $item = QuoteItem::firstOrFail();

        $product->update(['name' => 'Cambiado', 'price' => '999.00', 'tax_rate' => '50.00']);

        $item = $item->fresh();
        $this->assertSame('Original', $item->description);
        $this->assertEquals('100.00', $item->unit_price);
        $this->assertEquals('10.00', $item->tax_rate);
    }

    public function test_fixed_discount_and_decimal_quantity(): void
    {
        $user = $this->fullSalesAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->post('/quotes', $this->quotePayload($company, [], [
            $this->itemPayload(['quantity' => 1.5, 'unit_price' => '200.00', 'discount_type' => 'fixed', 'discount_value' => '50.00', 'tax_rate' => 0]),
        ]))->assertRedirect();

        // 1.5×200=300, -50 fijo =250, sin impuesto.
        $quote = Quote::firstOrFail();
        $this->assertEquals('300.00', $quote->subtotal);
        $this->assertEquals('50.00', $quote->discount_total);
        $this->assertEquals('250.00', $quote->total);
    }

    public function test_fixed_discount_above_base_rejected_and_nothing_persisted(): void
    {
        $user = $this->fullSalesAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)->from('/quotes/create')->post('/quotes', $this->quotePayload($company, [], [
            $this->itemPayload(['quantity' => 1, 'unit_price' => '100.00', 'discount_type' => 'fixed', 'discount_value' => '500.00', 'tax_rate' => 0]),
        ]));

        $response->assertRedirect('/quotes/create');
        $response->assertSessionHasErrors('items.0.discount_value');
        $this->assertSame(0, Quote::count());
        $this->assertSame(0, QuoteItem::count());
    }

    public function test_update_draft_replaces_items_transactionally(): void
    {
        $user = $this->fullSalesAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->post('/quotes', $this->quotePayload($company, [], [
            $this->itemPayload(['quantity' => 1, 'unit_price' => '100.00']),
            $this->itemPayload(['quantity' => 1, 'unit_price' => '200.00']),
        ]))->assertRedirect();
        $quote = Quote::firstOrFail();
        $this->assertEquals('300.00', $quote->total);
        $number = $quote->number;

        $response = $this->actingAs($user)->put("/quotes/{$quote->id}", $this->quotePayload($company, [], [
            $this->itemPayload(['quantity' => 3, 'unit_price' => '50.00', 'tax_rate' => 10]),
        ]));

        $response->assertRedirect(route('quotes.show', $quote));
        $quote = $quote->fresh();
        $this->assertSame($number, $quote->number);
        $this->assertSame(1, $quote->items()->count());
        $this->assertEquals('165.00', $quote->total); // 150 + 15 impuesto
    }

    public function test_accepted_quote_is_immutable(): void
    {
        $user = $this->fullSalesAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->post('/quotes', $this->quotePayload($company))->assertRedirect();
        $quote = Quote::firstOrFail();
        $this->actingAs($user)->patch("/quotes/{$quote->id}/accept")->assertRedirect();

        $this->actingAs($user)->put("/quotes/{$quote->id}", $this->quotePayload($company))->assertForbidden();
        $this->actingAs($user)->get("/quotes/{$quote->id}/edit")->assertRedirect();
        $this->assertEquals('200.00', $quote->fresh()->total);
    }

    // ---------------- Relaciones ----------------

    public function test_company_required_and_relations_must_match(): void
    {
        $user = $this->fullSalesAccess();
        $companyA = Company::factory()->create(['owner_id' => $user->id]);
        $companyB = Company::factory()->create(['owner_id' => $user->id]);
        $contactB = Contact::factory()->create(['company_id' => $companyB->id, 'owner_id' => $user->id]);
        $pipeline = Pipeline::where('name', 'Ventas')->firstOrFail();
        $stage = $pipeline->stages()->orderBy('position')->firstOrFail();
        $oppB = Opportunity::factory()->create([
            'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id,
            'company_id' => $companyB->id, 'owner_id' => $user->id,
        ]);

        // Contacto de otra empresa: rechazado, nada persiste.
        $this->actingAs($user)->from('/quotes/create')->post('/quotes', $this->quotePayload($companyA, [
            'contact_id' => $contactB->id,
        ]))->assertSessionHasErrors('contact_id');
        $this->assertSame(0, Quote::count());

        // Oportunidad de otra empresa: rechazado.
        $this->actingAs($user)->from('/quotes/create')->post('/quotes', $this->quotePayload($companyA, [
            'opportunity_id' => $oppB->id,
        ]))->assertSessionHasErrors('opportunity_id');
        $this->assertSame(0, Quote::count());
    }

    public function test_quote_from_opportunity_prefills_and_links(): void
    {
        $user = $this->fullSalesAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);
        $contact = Contact::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id]);
        $pipeline = Pipeline::where('name', 'Ventas')->firstOrFail();
        $stage = $pipeline->stages()->orderBy('position')->firstOrFail();
        $opp = Opportunity::factory()->create([
            'name' => 'Opp Prefill', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id,
            'company_id' => $company->id, 'contact_id' => $contact->id,
            'owner_id' => $user->id, 'currency' => 'COP',
        ]);

        $this->actingAs($user)->get("/quotes/create?opportunity={$opp->id}")->assertOk();

        $this->actingAs($user)->post('/quotes', $this->quotePayload($company, [
            'contact_id' => $contact->id,
            'opportunity_id' => $opp->id,
            'owner_id' => $user->id,
            'currency' => 'COP',
        ]))->assertRedirect();

        $quote = Quote::firstOrFail();
        $this->assertSame($opp->id, $quote->opportunity_id);
        $this->assertSame('COP', $quote->currency);

        // Visible en la ficha de la oportunidad.
        $this->actingAs($user)->get("/opportunities/{$opp->id}")
            ->assertOk()->assertSee($quote->number);
    }

    // ---------------- Estados ----------------

    public function test_status_transitions_with_timestamps_and_activity(): void
    {
        $user = $this->fullSalesAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->post('/quotes', $this->quotePayload($company))->assertRedirect();
        $quote = Quote::firstOrFail();

        $this->actingAs($user)->patch("/quotes/{$quote->id}/send")->assertRedirect();
        $this->assertSame('sent', $quote->fresh()->status);

        $this->actingAs($user)->patch("/quotes/{$quote->id}/accept")->assertRedirect();
        $quote = $quote->fresh();
        $this->assertSame('accepted', $quote->status);
        $this->assertNotNull($quote->accepted_at);
        $this->assertNull($quote->rejected_at);

        // Terminal: no se puede rechazar una aceptada.
        $this->actingAs($user)->from("/quotes/{$quote->id}")
            ->patch("/quotes/{$quote->id}/reject")->assertSessionHasErrors('status');
        $this->assertSame('accepted', $quote->fresh()->status);

        // Actividades registradas en la empresa.
        $this->assertTrue($company->activities()->where('subject', 'like', 'Cotización Q-%')->exists());
    }

    public function test_reject_sets_timestamp(): void
    {
        $user = $this->fullSalesAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->post('/quotes', $this->quotePayload($company))->assertRedirect();
        $quote = Quote::firstOrFail();

        $this->actingAs($user)->patch("/quotes/{$quote->id}/reject")->assertRedirect();
        $quote = $quote->fresh();
        $this->assertSame('rejected', $quote->status);
        $this->assertNotNull($quote->rejected_at);
        $this->assertNull($quote->accepted_at);
    }

    public function test_expired_is_computed_and_blocks_send(): void
    {
        $user = $this->fullSalesAccess();
        $quote = Quote::factory()->create([
            'status' => 'draft',
            'valid_until' => now()->subDay()->format('Y-m-d'),
            'owner_id' => $user->id,
        ]);

        $this->assertTrue($quote->is_expired);
        $this->actingAs($user)->get("/quotes/{$quote->id}")->assertOk()->assertSee('Vencida');

        $this->actingAs($user)->from("/quotes/{$quote->id}")
            ->patch("/quotes/{$quote->id}/send")->assertSessionHasErrors('status');
        $this->assertSame('draft', $quote->fresh()->status);
    }

    // ---------------- Listado/búsqueda/filtros ----------------

    public function test_search_filter_sort_paginate_quotes(): void
    {
        $user = $this->fullSalesAccess();
        $company = Company::factory()->create(['trade_name' => 'BuscadaQ S.A.S.', 'owner_id' => $user->id]);
        $contact = Contact::factory()->create(['first_name' => 'Cotiza', 'last_name' => 'Dor', 'company_id' => $company->id, 'owner_id' => $user->id]);

        $this->actingAs($user)->post('/quotes', $this->quotePayload($company, ['contact_id' => $contact->id, 'owner_id' => $user->id]))->assertRedirect();
        $mine = Quote::firstOrFail()->number;

        $other = Company::factory()->create(['trade_name' => 'Otra S.A.S.', 'owner_id' => $user->id]);
        $this->actingAs($user)->post('/quotes', $this->quotePayload($other, ['owner_id' => $user->id]))->assertRedirect();

        $response = $this->actingAs($user)->get('/quotes?search=buscadaq');
        $response->assertOk()->assertSee($mine);

        $response = $this->actingAs($user)->get(
            "/quotes?status=draft&company_id={$company->id}&owner_id={$user->id}&currency=USD"
            .'&issued_from='.now()->format('Y-m-d').'&issued_to='.now()->addDay()->format('Y-m-d')
        );
        $response->assertOk()->assertSee($mine);

        $response = $this->actingAs($user)->get('/quotes?sort=total&direction=desc');
        $response->assertOk();

        Quote::query()->delete();
        $company2 = Company::factory()->create(['owner_id' => $user->id]);
        for ($i = 0; $i < 16; $i++) {
            Quote::factory()->create(['company_id' => $company2->id, 'owner_id' => $user->id]);
        }
        $this->assertCount(15, $this->actingAs($user)->get('/quotes')->viewData('quotes')->items());
        $this->assertCount(1, $this->actingAs($user)->get('/quotes?page=2')->viewData('quotes')->items());
    }

    public function test_company_contact_and_dashboard_integrations(): void
    {
        $user = $this->fullSalesAccess();
        $company = Company::factory()->create(['trade_name' => 'Integra S.A.S.', 'owner_id' => $user->id]);
        $contact = Contact::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id]);

        $this->actingAs($user)->post('/quotes', $this->quotePayload($company, [
            'contact_id' => $contact->id,
            'valid_until' => now()->addDays(3)->format('Y-m-d'),
        ]))->assertRedirect();
        $quote = Quote::firstOrFail();

        $this->actingAs($user)->get("/companies/{$company->id}")->assertOk()->assertSee($quote->number);
        $this->actingAs($user)->get("/contacts/{$contact->id}")->assertOk()->assertSee($quote->number);
        $this->actingAs($user)->get("/products/{$this->makeProduct()->id}")->assertOk();

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee($quote->number);
        $this->actingAs($user)->get("/quotes/{$quote->id}/print")->assertOk()->assertSee($quote->number);
    }

    public function test_product_usage_count(): void
    {
        $user = $this->fullSalesAccess();
        $product = $this->makeProduct();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->post('/quotes', $this->quotePayload($company, [], [
            $this->itemPayload(['product_id' => $product->id, 'description' => null, 'unit_price' => null, 'tax_rate' => null]),
        ]))->assertRedirect();

        $this->actingAs($user)->get("/products/{$product->id}")
            ->assertOk()->assertSee('Uso en cotizaciones (1)');
    }
}
