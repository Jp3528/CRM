<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\DataScope;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase 14 — Usuarios, Equipos y Control de Acceso.
 * P14A: Gestión integral de usuarios, asignación de roles, prevención de escalada.
 * P14B: Equipos, asignación de integrantes y aislamiento de alcance.
 * P14C: Matriz de roles y permisos con bloqueo estricto del rol Consulta.
 */
class PhaseFourteenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        DataScope::clearCache();
    }

    private function makeUser(array $roles = [], array $perms = [], ?Team $team = null, string $status = 'active'): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'status' => $status,
            'team_id' => $team?->id,
        ]);

        foreach ($roles as $roleName) {
            $role = Role::where('name', $roleName)->firstOrFail();
            $user->roles()->attach($role->id);
        }

        if (! empty($perms)) {
            $permissionIds = Permission::whereIn('name', $perms)->pluck('id')->all();
            $user->permissions()->sync($permissionIds);
        }

        return $user;
    }

    public function test_superadmin_can_view_users_list_and_details(): void
    {
        $super = $this->makeUser(['Superadministrador']);
        $target = $this->makeUser(['Vendedor'], ['companies.view']);

        $response = $this->actingAs($super)->get(route('admin.users.index'));
        $response->assertOk();
        $response->assertSee($target->name);

        $showResponse = $this->actingAs($super)->get(route('admin.users.show', $target));
        $showResponse->assertOk();
        $showResponse->assertSee($target->email);
    }

    public function test_admin_with_permission_can_search_and_filter_users(): void
    {
        $admin = $this->makeUser(['Administrador'], ['users.view']);
        $teamA = Team::create(['name' => 'Team Alpha', 'slug' => 'team-alpha', 'status' => 'active']);
        $userAlpha = $this->makeUser(['Vendedor'], [], $teamA);
        $userBeta = $this->makeUser(['Vendedor'], [], null, 'inactive');

        $searchResp = $this->actingAs($admin)->get(route('admin.users.index', ['search' => $userAlpha->name]));
        $searchResp->assertOk();
        $searchResp->assertSee($userAlpha->name);

        $filterResp = $this->actingAs($admin)->get(route('admin.users.index', ['status' => 'inactive']));
        $filterResp->assertOk();
        $filterResp->assertSee($userBeta->name);
    }

    public function test_unauthorized_user_cannot_view_users(): void
    {
        $vendedor = $this->makeUser(['Vendedor']);

        $response = $this->actingAs($vendedor)->get(route('admin.users.index'));
        $response->assertForbidden();
    }

    public function test_superadmin_can_create_user_with_role_and_team(): void
    {
        $super = $this->makeUser(['Superadministrador']);
        $team = Team::create(['name' => 'Comercial Norte', 'slug' => 'comercial-norte', 'status' => 'active']);
        $role = Role::where('name', 'Vendedor')->firstOrFail();

        $response = $this->actingAs($super)->post(route('admin.users.store'), [
            'name' => 'Carlos Vendedor',
            'email' => 'carlos@crm.local',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'team_id' => $team->id,
            'status' => 'active',
            'roles' => [$role->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'carlos@crm.local', 'team_id' => $team->id]);
        $created = User::where('email', 'carlos@crm.local')->firstOrFail();
        $this->assertTrue($created->hasRole('Vendedor'));
    }

    public function test_regular_admin_cannot_escalate_and_create_superadmin(): void
    {
        $admin = $this->makeUser(['Administrador'], ['users.create']);
        $superRole = Role::where('name', 'Superadministrador')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Hacker Admin',
            'email' => 'hacker@crm.local',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'status' => 'active',
            'roles' => [$superRole->id],
        ]);

        $response->assertSessionHasErrors('roles');
        $this->assertDatabaseMissing('users', ['email' => 'hacker@crm.local']);
    }

    public function test_regular_admin_cannot_edit_or_delete_superadmin(): void
    {
        $admin = $this->makeUser(['Administrador'], ['users.view', 'users.update', 'users.delete']);
        $super = $this->makeUser(['Superadministrador']);

        $editResp = $this->actingAs($admin)->get(route('admin.users.edit', $super));
        $editResp->assertForbidden();

        $updateResp = $this->actingAs($admin)->put(route('admin.users.update', $super), [
            'name' => 'Superadmin Modificado',
            'email' => $super->email,
            'status' => 'active',
        ]);
        $updateResp->assertForbidden();

        $deleteResp = $this->actingAs($admin)->delete(route('admin.users.destroy', $super));
        $deleteResp->assertForbidden();
    }

    public function test_last_active_superadmin_cannot_be_deactivated(): void
    {
        $super = $this->makeUser(['Superadministrador']);

        $response = $this->actingAs($super)->put(route('admin.users.update', $super), [
            'name' => $super->name,
            'email' => $super->email,
            'status' => 'inactive',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertEquals('active', $super->fresh()->status);
    }

    public function test_last_active_superadmin_cannot_be_deleted(): void
    {
        $super1 = $this->makeUser(['Superadministrador']);
        $super2 = $this->makeUser(['Superadministrador']);

        // Con 2 superadministradores, super1 puede eliminar a super2
        $resp1 = $this->actingAs($super1)->delete(route('admin.users.destroy', $super2));
        $resp1->assertRedirect(route('admin.users.index'));
        $this->assertSoftDeleted('users', ['id' => $super2->id]);

        // Ahora solo queda super1; no puede eliminarse a sí mismo
        $selfResp = $this->actingAs($super1)->delete(route('admin.users.destroy', $super1));
        $selfResp->assertSessionHasErrors('user');
    }

    public function test_last_active_superadmin_cannot_lose_superadmin_role(): void
    {
        $super = $this->makeUser(['Superadministrador']);
        $vendedorRole = Role::where('name', 'Vendedor')->firstOrFail();

        $response = $this->actingAs($super)->put(route('admin.users.update', $super), [
            'name' => $super->name,
            'email' => $super->email,
            'status' => 'active',
            'roles' => [$vendedorRole->id],
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertTrue($super->fresh()->hasRole('Superadministrador'));
    }

    public function test_deactivated_user_is_blocked_by_active_middleware(): void
    {
        $user = $this->makeUser(['Vendedor'], ['companies.view'], null, 'inactive');

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_soft_deleted_user_preserves_commercial_data(): void
    {
        $super = $this->makeUser(['Superadministrador']);
        $vendedor = $this->makeUser(['Vendedor']);

        $company = Company::factory()->create([
            'owner_id' => $vendedor->id,
            'trade_name' => 'Empresa Histórica SAC',
            'legal_name' => 'Empresa Histórica SAC',
        ]);

        $this->actingAs($super)->delete(route('admin.users.destroy', $vendedor));

        $this->assertSoftDeleted('users', ['id' => $vendedor->id]);
        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'owner_id' => $vendedor->id,
            'deleted_at' => null,
        ]);
    }

    public function test_superadmin_can_crud_teams(): void
    {
        $super = $this->makeUser(['Superadministrador']);

        $storeResp = $this->actingAs($super)->post(route('teams.store'), [
            'name' => 'Equipo Metropolitano',
            'description' => 'Área comercial Lima',
            'status' => 'active',
        ]);
        $storeResp->assertRedirect();
        $this->assertDatabaseHas('teams', ['name' => 'Equipo Metropolitano', 'slug' => 'equipo-metropolitano']);

        $team = Team::where('slug', 'equipo-metropolitano')->firstOrFail();

        $updateResp = $this->actingAs($super)->put(route('teams.update', $team), [
            'name' => 'Equipo Metropolitano Actualizado',
            'status' => 'active',
        ]);
        $updateResp->assertRedirect();
        $this->assertDatabaseHas('teams', ['name' => 'Equipo Metropolitano Actualizado']);
    }

    public function test_supervisor_can_only_view_their_own_team(): void
    {
        $team1 = Team::create(['name' => 'Equipo 1', 'slug' => 'equipo-1', 'status' => 'active']);
        $team2 = Team::create(['name' => 'Equipo 2', 'slug' => 'equipo-2', 'status' => 'active']);

        $supervisor = $this->makeUser(['Supervisor'], ['teams.view'], $team1);

        $indexResp = $this->actingAs($supervisor)->get(route('teams.index'));
        $indexResp->assertOk();
        $indexResp->assertSee('Equipo 1');
        $indexResp->assertDontSee('Equipo 2');

        $showOkResp = $this->actingAs($supervisor)->get(route('teams.show', $team1));
        $showOkResp->assertOk();

        $showForbiddenResp = $this->actingAs($supervisor)->get(route('teams.show', $team2));
        $showForbiddenResp->assertForbidden();
    }

    public function test_cannot_delete_team_with_active_members(): void
    {
        $super = $this->makeUser(['Superadministrador']);
        $team = Team::create(['name' => 'Equipo Con Gente', 'slug' => 'equipo-con-gente', 'status' => 'active']);
        $member = $this->makeUser(['Vendedor'], [], $team);

        $deleteResp = $this->actingAs($super)->delete(route('teams.destroy', $team));
        $deleteResp->assertSessionHasErrors('team');
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
    }

    public function test_assign_and_remove_members_from_team(): void
    {
        $super = $this->makeUser(['Superadministrador']);
        $team = Team::create(['name' => 'Equipo Ventas B2B', 'slug' => 'equipo-ventas-b2b', 'status' => 'active']);
        $user = $this->makeUser(['Vendedor']);

        $this->assertNull($user->team_id);

        $assignResp = $this->actingAs($super)->post(route('teams.members.store', $team), [
            'user_id' => $user->id,
        ]);
        $assignResp->assertRedirect(route('teams.show', $team));
        $this->assertEquals($team->id, $user->fresh()->team_id);

        $removeResp = $this->actingAs($super)->delete(route('teams.members.destroy', [$team, $user]));
        $removeResp->assertRedirect(route('teams.show', $team));
        $this->assertNull($user->fresh()->team_id);
    }

    public function test_null_team_users_do_not_share_scope(): void
    {
        $user1 = $this->makeUser(['Supervisor'], ['companies.view'], null);
        $user2 = $this->makeUser(['Supervisor'], ['companies.view'], null);

        $company1 = Company::factory()->create([
            'owner_id' => $user1->id,
            'trade_name' => 'Empresa de User 1',
            'legal_name' => 'Empresa de User 1',
        ]);
        $company2 = Company::factory()->create([
            'owner_id' => $user2->id,
            'trade_name' => 'Empresa de User 2',
            'legal_name' => 'Empresa de User 2',
        ]);

        $resp1 = $this->actingAs($user1)->get(route('companies.index'));
        $resp1->assertOk();
        $resp1->assertSee($company1->trade_name);
        $resp1->assertDontSee($company2->trade_name);
    }

    public function test_role_matrix_accessible_by_roles_view_permission(): void
    {
        $super = $this->makeUser(['Superadministrador']);

        $response = $this->actingAs($super)->get(route('roles.index'));
        $response->assertOk();
        $response->assertSee('Superadministrador');
        $response->assertSee('Vendedor');
        $response->assertSee('Consulta');
    }

    public function test_only_superadmin_can_update_role_permissions(): void
    {
        $admin = $this->makeUser(['Administrador'], ['roles.view']);
        $super = $this->makeUser(['Superadministrador']);
        $role = Role::where('name', 'Vendedor')->firstOrFail();
        $perm = Permission::where('name', 'companies.view')->firstOrFail();

        $adminResp = $this->actingAs($admin)->put(route('roles.update', $role), [
            'permissions' => [$perm->id],
        ]);
        $adminResp->assertForbidden();

        $superResp = $this->actingAs($super)->put(route('roles.update', $role), [
            'permissions' => [$perm->id],
        ]);
        $superResp->assertRedirect(route('roles.index'));
        $this->assertEquals([$perm->id], $role->fresh()->permissions->pluck('id')->all());
    }

    public function test_superadmin_role_cannot_lose_permissions(): void
    {
        $superUser = $this->makeUser(['Superadministrador']);
        $superRole = Role::where('name', 'Superadministrador')->firstOrFail();

        $allPermCount = Permission::count();

        // Intento de vaciar los permisos del rol Superadministrador
        $response = $this->actingAs($superUser)->put(route('roles.update', $superRole), [
            'permissions' => [],
        ]);
        $response->assertRedirect(route('roles.index'));

        // El controlador garantiza que conserva todos los permisos
        $this->assertEquals($allPermCount, $superRole->fresh()->permissions()->count());
    }

    public function test_role_reset_restores_default_permissions(): void
    {
        $super = $this->makeUser(['Superadministrador']);
        $soporteRole = Role::where('name', 'Soporte')->firstOrFail();

        // Modificamos a ningún permiso
        $soporteRole->permissions()->sync([]);
        $this->assertCount(0, $soporteRole->fresh()->permissions);

        // Reseteamos
        $response = $this->actingAs($super)->post(route('roles.reset', $soporteRole));
        $response->assertRedirect(route('roles.index'));

        $this->assertTrue($soporteRole->fresh()->permissions->contains('name', 'tickets.view'));
    }

    public function test_pure_consulta_user_strictly_blocked_from_write_operations(): void
    {
        // Usuario con rol 'Consulta' puro al que accidentalmente se le asigna permiso 'companies.create'
        $consultaUser = $this->makeUser(['Consulta'], ['companies.view', 'companies.create']);

        // Gate::before y DataScope bloquean toda acción de escritura
        $response = $this->actingAs($consultaUser)->post(route('companies.store'), [
            'name' => 'Empresa No Permitida',
            'phone' => '123456789',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('companies', ['name' => 'Empresa No Permitida']);
    }
}
