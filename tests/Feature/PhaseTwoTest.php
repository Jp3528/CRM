<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseTwoTest extends TestCase
{
    use RefreshDatabase;

    private function seedRbac(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    }

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'password' => Hash::make('password'),
            'status' => 'active',
        ], $attrs));
    }

    private function superAdmin(): User
    {
        $this->seedRbac();
        $user = $this->makeUser();
        $user->roles()->attach(Role::where('name', 'Superadministrador')->firstOrFail());

        return $user;
    }

    public function test_active_user_can_login(): void
    {
        $this->seedRbac();
        $user = $this->makeUser(['email' => 'activo@nexuscrm.local']);

        $response = $this->post('/login', [
            'email' => 'activo@nexuscrm.local',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_rejected(): void
    {
        $this->seedRbac();
        $this->makeUser(['email' => 'activo@nexuscrm.local']);

        $response = $this->from('/login')->post('/login', [
            'email' => 'activo@nexuscrm.local',
            'password' => 'incorrecta',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->seedRbac();
        $this->makeUser(['email' => 'baja@nexuscrm.local', 'status' => 'inactive']);

        $response = $this->from('/login')->post('/login', [
            'email' => 'baja@nexuscrm.local',
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_works(): void
    {
        $this->seedRbac();
        $user = $this->makeUser();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_protected_routes_redirect_guests(): void
    {
        $this->seedRbac();

        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/profile')->assertRedirect('/login');
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_rbac_helpers_work(): void
    {
        $this->seedRbac();
        $user = $this->makeUser();
        $role = Role::where('name', 'Vendedor')->firstOrFail();
        $permission = Permission::where('name', 'leads.view')->firstOrFail();

        $user->roles()->attach($role);
        $this->assertTrue($user->hasRole('Vendedor'));
        $this->assertTrue($user->hasAnyRole(['Soporte', 'Vendedor']));
        $this->assertFalse($user->hasRole('Administrador'));

        // Permiso vía rol.
        $role->permissions()->attach($permission);
        $this->assertTrue($user->fresh()->hasPermission('leads.view'));

        // Permiso directo.
        $other = Permission::where('name', 'contacts.view')->firstOrFail();
        $user->permissions()->attach($other);
        $this->assertTrue($user->fresh()->hasPermission('contacts.view'));
        $this->assertTrue($user->fresh()->hasAnyPermission(['no.existe', 'contacts.view']));
        $this->assertFalse($user->fresh()->hasPermission('users.delete'));
    }

    public function test_superadmin_has_full_access(): void
    {
        $user = $this->superAdmin();

        $this->assertTrue($user->hasPermission('cualquier.cosa'));
        $this->assertTrue($user->isSuperAdmin());

        $this->actingAs($user)->get('/admin/users')->assertOk();
        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_user_without_permission_gets_403(): void
    {
        $this->seedRbac();
        $user = $this->makeUser();

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    public function test_password_change_works(): void
    {
        $this->seedRbac();
        $user = $this->makeUser();

        $response = $this->actingAs($user)->put('/password', [
            'current_password' => 'password',
            'password' => 'nueva-clave-123',
            'password_confirmation' => 'nueva-clave-123',
        ]);

        $response->assertRedirect(route('profile.edit'));
        $this->assertTrue(Hash::check('nueva-clave-123', $user->fresh()->password));
    }

    public function test_profile_requires_auth(): void
    {
        $this->seedRbac();

        $this->get('/profile')->assertRedirect('/login');
        $this->patch('/profile', ['name' => 'X', 'email' => 'x@x.local'])->assertRedirect('/login');
    }

    public function test_inactive_user_blocked_on_authenticated_requests(): void
    {
        $this->seedRbac();
        $user = $this->makeUser(['status' => 'active']);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->update(['status' => 'inactive']);

        $response = $this->actingAs($user->fresh())->get('/dashboard');
        $response->assertRedirect(route('login'));
    }

    public function test_no_public_registration(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_password_reset_pages_render(): void
    {
        $this->get('/forgot-password')->assertOk();
        $this->get('/reset-password/token-demo')->assertOk();
    }
}
