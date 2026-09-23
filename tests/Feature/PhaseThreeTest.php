<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Permission;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseThreeTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $ids = Permission::whereIn('name', $permissions)->pluck('id')->all();
        $user->permissions()->sync($ids);

        return $user;
    }

    private function fullAccessUser(): User
    {
        return $this->userWith([
            'companies.view', 'companies.create', 'companies.update', 'companies.delete',
            'contacts.view', 'contacts.create', 'contacts.update', 'contacts.delete',
        ]);
    }

    private function companyPayload(array $overrides = []): array
    {
        return array_merge([
            'trade_name' => 'Acme S.A.S.',
            'legal_name' => 'Acme S.A.S.',
            'tax_id' => '900123456-7',
            'email' => 'contacto@acme.com',
            'phone' => '+57 601 555 0101',
            'website' => 'https://acme.example.com',
            'industry' => 'Tecnología',
            'company_size' => '11-50',
            'address' => 'Calle 100 # 15-20',
            'city' => 'Bogotá',
            'region' => 'Cundinamarca',
            'country' => 'Colombia',
            'postal_code' => '110111',
            'status' => 'active',
            'notes' => 'Cliente potencial.',
        ], $overrides);
    }

    private function contactPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'email' => 'juan.perez@example.com',
            'phone' => '+57 601 555 0202',
            'mobile' => '+57 300 555 0202',
            'job_title' => 'Gerente de compras',
            'department' => 'Ventas',
            'status' => 'active',
            'notes' => 'Contacto principal.',
        ], $overrides);
    }

    // ---------------- Empresas ----------------

    public function test_authorized_user_sees_companies_list(): void
    {
        $user = $this->fullAccessUser();
        Company::factory()->create(['trade_name' => 'Listada S.A.S.', 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->get('/companies');

        $response->assertOk();
        $response->assertSee('Listada S.A.S.');
    }

    public function test_user_without_permission_cannot_access_companies(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $user = User::factory()->create(['status' => 'active']);
        $company = Company::factory()->create();

        $this->actingAs($user)->get('/companies')->assertForbidden();
        $this->actingAs($user)->get('/companies/create')->assertForbidden();
        $this->actingAs($user)->post('/companies', $this->companyPayload())->assertForbidden();
        $this->actingAs($user)->get("/companies/{$company->id}")->assertForbidden();
        $this->actingAs($user)->get("/companies/{$company->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/companies/{$company->id}", $this->companyPayload())->assertForbidden();
        $this->actingAs($user)->delete("/companies/{$company->id}")->assertForbidden();
    }

    public function test_create_company(): void
    {
        $user = $this->fullAccessUser();

        $response = $this->actingAs($user)->post('/companies', $this->companyPayload());

        $company = Company::where('trade_name', 'Acme S.A.S.')->firstOrFail();
        $response->assertRedirect(route('companies.show', $company));
        $response->assertSessionHas('success', 'Empresa creada correctamente.');
        $this->assertDatabaseHas('companies', ['trade_name' => 'Acme S.A.S.', 'tax_id' => '900123456-7']);
    }

    public function test_create_company_validation(): void
    {
        $user = $this->fullAccessUser();

        $response = $this->actingAs($user)->from('/companies/create')->post('/companies', $this->companyPayload([
            'trade_name' => '',
            'email' => 'no-es-email',
            'website' => 'no-es-url',
            'status' => 'inexistente',
        ]));

        $response->assertRedirect('/companies/create');
        $response->assertSessionHasErrors(['trade_name', 'email', 'website', 'status']);
        $this->assertDatabaseMissing('companies', ['email' => 'no-es-email']);
    }

    public function test_show_company(): void
    {
        $user = $this->fullAccessUser();
        $company = Company::factory()->create(['trade_name' => 'Visible S.A.S.', 'owner_id' => $user->id]);

        $this->actingAs($user)->get("/companies/{$company->id}")
            ->assertOk()
            ->assertSee('Visible S.A.S.');
    }

    public function test_edit_company_page_renders(): void
    {
        $user = $this->fullAccessUser();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->get("/companies/{$company->id}/edit")->assertOk();
    }

    public function test_update_company(): void
    {
        $user = $this->fullAccessUser();
        $company = Company::factory()->create(['trade_name' => 'Antes S.A.S.', 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->put(
            "/companies/{$company->id}",
            $this->companyPayload(['trade_name' => 'Después S.A.S.', 'tax_id' => $company->tax_id])
        );

        $response->assertRedirect(route('companies.show', $company));
        $response->assertSessionHas('success', 'Empresa actualizada correctamente.');
        $this->assertSame('Después S.A.S.', $company->fresh()->trade_name);
    }

    public function test_delete_company_soft_deletes_and_keeps_contacts(): void
    {
        $user = $this->fullAccessUser();
        $company = Company::factory()->create(['owner_id' => $user->id]);
        $contact = Contact::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->delete("/companies/{$company->id}");

        $response->assertRedirect(route('companies.index'));
        $response->assertSessionHas('success', 'Empresa eliminada correctamente.');
        $this->assertSoftDeleted('companies', ['id' => $company->id]);
        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'deleted_at' => null]);
    }

    public function test_search_company(): void
    {
        $user = $this->fullAccessUser();
        Company::factory()->create(['trade_name' => 'BuscadaXYZ S.A.S.', 'email' => 'x@y.com', 'phone' => '111', 'tax_id' => '900111111-1', 'legal_name' => 'Legal X', 'owner_id' => $user->id]);
        Company::factory()->create(['trade_name' => 'Otra S.A.S.', 'email' => 'otra@example.com', 'phone' => '222', 'tax_id' => '900222222-2', 'legal_name' => 'Legal Y', 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->get('/companies?search=buscadaxyz');

        $response->assertOk();
        $response->assertSee('BuscadaXYZ S.A.S.');
        $response->assertDontSee('Otra S.A.S.');
    }

    public function test_filter_company(): void
    {
        $user = $this->fullAccessUser();
        $other = User::factory()->create(['status' => 'active']);
        Company::factory()->create([
            'trade_name' => 'Filtrada S.A.S.', 'status' => 'active', 'industry' => 'Tecnología',
            'country' => 'Colombia', 'owner_id' => $user->id,
        ]);
        Company::factory()->create([
            'trade_name' => 'Descartada S.A.S.', 'status' => 'inactive', 'industry' => 'Salud',
            'country' => 'México', 'owner_id' => $other->id,
        ]);

        $response = $this->actingAs($user)->get('/companies?status=active&industry=Tecnología&country=Colombia&owner_id='.$user->id);

        $response->assertOk();
        $response->assertSee('Filtrada S.A.S.');
        $response->assertDontSee('Descartada S.A.S.');
    }

    public function test_companies_pagination(): void
    {
        $user = $this->fullAccessUser();
        Company::factory(16)->create(['owner_id' => $user->id]);

        $pageOne = $this->actingAs($user)->get('/companies');
        $pageOne->assertOk();
        // 15 por página: la página 1 no muestra el registro 16.
        $this->assertCount(15, $pageOne->viewData('companies')->items());

        $pageTwo = $this->actingAs($user)->get('/companies?page=2');
        $pageTwo->assertOk();
        $this->assertCount(1, $pageTwo->viewData('companies')->items());
    }

    public function test_company_show_lists_its_contacts(): void
    {
        $user = $this->fullAccessUser();
        $company = Company::factory()->create(['owner_id' => $user->id]);
        $contact = Contact::factory()->create([
            'company_id' => $company->id, 'owner_id' => $user->id,
            'first_name' => 'Asociado', 'last_name' => 'Probado',
        ]);

        $this->actingAs($user)->get("/companies/{$company->id}")
            ->assertOk()
            ->assertSee('Asociado Probado');

        $this->assertTrue($company->contacts()->whereKey($contact->id)->exists());
    }

    // ---------------- Contactos ----------------

    public function test_authorized_user_sees_contacts_list(): void
    {
        $user = $this->fullAccessUser();
        Contact::factory()->create(['first_name' => 'Listado', 'last_name' => 'Visible', 'owner_id' => $user->id]);

        $this->actingAs($user)->get('/contacts')->assertOk()->assertSee('Listado Visible');
    }

    public function test_user_without_permission_cannot_access_contacts(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $user = User::factory()->create(['status' => 'active']);
        $contact = Contact::factory()->create();

        $this->actingAs($user)->get('/contacts')->assertForbidden();
        $this->actingAs($user)->get('/contacts/create')->assertForbidden();
        $this->actingAs($user)->post('/contacts', $this->contactPayload())->assertForbidden();
        $this->actingAs($user)->get("/contacts/{$contact->id}")->assertForbidden();
        $this->actingAs($user)->get("/contacts/{$contact->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/contacts/{$contact->id}", $this->contactPayload())->assertForbidden();
        $this->actingAs($user)->delete("/contacts/{$contact->id}")->assertForbidden();
    }

    public function test_create_contact(): void
    {
        $user = $this->fullAccessUser();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)->post('/contacts', $this->contactPayload([
            'company_id' => $company->id,
            'owner_id' => $user->id,
        ]));

        $contact = Contact::where('email', 'juan.perez@example.com')->firstOrFail();
        $response->assertRedirect(route('contacts.show', $contact));
        $response->assertSessionHas('success', 'Contacto creado correctamente.');
        $this->assertSame($company->id, $contact->company_id);
    }

    public function test_create_contact_validation(): void
    {
        $user = $this->fullAccessUser();

        $response = $this->actingAs($user)->from('/contacts/create')->post('/contacts', $this->contactPayload([
            'first_name' => '',
            'email' => 'mal',
            'company_id' => 999999,
            'status' => 'raro',
        ]));

        $response->assertRedirect('/contacts/create');
        $response->assertSessionHasErrors(['first_name', 'email', 'company_id', 'status']);
    }

    public function test_show_contact_displays_company_link(): void
    {
        $user = $this->fullAccessUser();
        $company = Company::factory()->create(['trade_name' => 'Empleadora S.A.S.', 'owner_id' => $user->id]);
        $contact = Contact::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id]);

        $this->actingAs($user)->get("/contacts/{$contact->id}")
            ->assertOk()
            ->assertSee('Empleadora S.A.S.');
    }

    public function test_update_contact_and_change_company(): void
    {
        $user = $this->fullAccessUser();
        $old = Company::factory()->create(['owner_id' => $user->id]);
        $new = Company::factory()->create(['owner_id' => $user->id]);
        $contact = Contact::factory()->create(['company_id' => $old->id, 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->put("/contacts/{$contact->id}", $this->contactPayload([
            'company_id' => $new->id,
            'job_title' => 'Directora',
        ]));

        $response->assertRedirect(route('contacts.show', $contact));
        $response->assertSessionHas('success', 'Contacto actualizado correctamente.');
        $this->assertSame($new->id, $contact->fresh()->company_id);
        $this->assertSame('Directora', $contact->fresh()->job_title);
    }

    public function test_delete_contact_soft_deletes(): void
    {
        $user = $this->fullAccessUser();
        $contact = Contact::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)->delete("/contacts/{$contact->id}");

        $response->assertRedirect(route('contacts.index'));
        $response->assertSessionHas('success', 'Contacto eliminado correctamente.');
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }

    public function test_search_contact_includes_company_name(): void
    {
        $user = $this->fullAccessUser();
        $company = Company::factory()->create(['trade_name' => 'EmpresaBuscada S.A.S.', 'owner_id' => $user->id]);
        Contact::factory()->create(['first_name' => 'Hallado', 'last_name' => 'PorEmpresa', 'company_id' => $company->id, 'owner_id' => $user->id]);
        Contact::factory()->create(['first_name' => 'Otro', 'last_name' => 'Cualquiera', 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->get('/contacts?search=empresabuscada');

        $response->assertOk();
        $response->assertSee('Hallado PorEmpresa');
        $response->assertDontSee('Otro Cualquiera');
    }

    public function test_filter_contacts(): void
    {
        $user = $this->fullAccessUser();
        $company = Company::factory()->create(['owner_id' => $user->id]);
        Contact::factory()->create([
            'first_name' => 'Filtrado', 'last_name' => 'Ok', 'status' => 'active',
            'department' => 'Ventas', 'company_id' => $company->id, 'owner_id' => $user->id,
        ]);
        Contact::factory()->create([
            'first_name' => 'Descartado', 'last_name' => 'No', 'status' => 'inactive',
            'department' => 'TI', 'owner_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(
            "/contacts?status=active&company_id={$company->id}&department=Ventas&owner_id={$user->id}"
        );

        $response->assertOk();
        $response->assertSee('Filtrado Ok');
        $response->assertDontSee('Descartado No');
    }

    public function test_contacts_pagination(): void
    {
        $user = $this->fullAccessUser();
        Contact::factory(16)->create(['owner_id' => $user->id]);

        $pageOne = $this->actingAs($user)->get('/contacts');
        $pageOne->assertOk();
        $this->assertCount(15, $pageOne->viewData('contacts')->items());

        $pageTwo = $this->actingAs($user)->get('/contacts?page=2');
        $pageTwo->assertOk();
        $this->assertCount(1, $pageTwo->viewData('contacts')->items());
    }

    // ---------------- Tags y relaciones ----------------

    public function test_company_contact_relationship(): void
    {
        $company = Company::factory()->create();
        $contact = Contact::factory()->create(['company_id' => $company->id]);

        $this->assertTrue($company->contacts()->whereKey($contact->id)->exists());
        $this->assertSame($company->id, $contact->company->id);
    }

    public function test_tags_can_be_assigned_and_removed(): void
    {
        $user = $this->fullAccessUser();
        $tag = Tag::create(['name' => 'VIP', 'slug' => 'vip']);

        // Asignar existente al crear.
        $this->actingAs($user)->post('/companies', $this->companyPayload(['tags' => [$tag->id]]))
            ->assertRedirect();
        $company = Company::where('trade_name', 'Acme S.A.S.')->firstOrFail();
        $this->assertTrue($company->tags()->whereKey($tag->id)->exists());

        // Crear nueva vía texto libre al actualizar.
        $this->actingAs($user)->put(
            "/companies/{$company->id}",
            $this->companyPayload(['trade_name' => 'Acme S.A.S.', 'tax_id' => $company->tax_id, 'new_tags' => 'Bogotá'])
        )->assertRedirect();
        $this->assertTrue($company->fresh()->tags()->where('slug', 'bogota')->exists());

        // Quitar.
        $this->actingAs($user)->delete("/companies/{$company->id}/tags/{$tag->id}")->assertRedirect();
        $this->assertFalse($company->fresh()->tags()->whereKey($tag->id)->exists());

        // Contactos: asignar y quitar.
        $contact = Contact::factory()->create(['owner_id' => $user->id]);
        $this->actingAs($user)->put("/contacts/{$contact->id}", $this->contactPayload(['tags' => [$tag->id]]))
            ->assertRedirect();
        $this->assertTrue($contact->fresh()->tags()->whereKey($tag->id)->exists());

        $this->actingAs($user)->delete("/contacts/{$contact->id}/tags/{$tag->id}")->assertRedirect();
        $this->assertFalse($contact->fresh()->tags()->whereKey($tag->id)->exists());
    }

    public function test_company_show_displays_recent_activity(): void
    {
        $user = $this->fullAccessUser();
        $company = Company::factory()->create(['owner_id' => $user->id]);
        $company->activities()->create([
            'type' => 'call',
            'subject' => 'Llamada de seguimiento',
            'description' => 'Cliente interesado.',
            'status' => 'completed',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)->get("/companies/{$company->id}")
            ->assertOk()
            ->assertSee('Llamada de seguimiento');
    }
}
