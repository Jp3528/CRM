<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Observers\AutomationObserver;
use App\Services\Automations\AutomationTriggerDispatcher;
use App\Support\DataScope;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseElevenAfterCommitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->app[Kernel::class]->setArtisan(null);
        DataScope::clearCache();
        AutomationObserver::reset();
        AutomationTriggerDispatcher::disableSync();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        AutomationTriggerDispatcher::disableSync();
        AutomationObserver::reset();
        $this->artisan('migrate:fresh');
        $this->app[Kernel::class]->setArtisan(null);
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_dispatch_runs_after_commit_and_not_after_rollback(): void
    {
        $user = $this->automationOwner();
        $this->makeAutomation($user);

        DB::beginTransaction();
        Lead::factory()->create(['owner_id' => $user->id]);

        $this->assertSame(0, AutomationRun::count());
        $this->assertSame(0, Task::count());

        DB::commit();

        $this->assertSame(1, AutomationRun::count());
        $this->assertSame(1, Task::count());

        DB::beginTransaction();
        Lead::factory()->create(['owner_id' => $user->id]);

        $this->assertSame(1, AutomationRun::count());
        $this->assertSame(1, Task::count());

        DB::rollBack();

        $this->assertSame(1, AutomationRun::count());
        $this->assertSame(1, Task::count());
    }

    public function test_nested_transaction_waits_for_outer_commit(): void
    {
        $user = $this->automationOwner();
        $this->makeAutomation($user);

        DB::beginTransaction();

        DB::transaction(function () use ($user) {
            Lead::factory()->create(['owner_id' => $user->id]);
        });

        $this->assertSame(0, AutomationRun::count());
        $this->assertSame(0, Task::count());

        DB::commit();

        $this->assertSame(1, AutomationRun::count());
        $this->assertSame(1, Task::count());
    }

    private function automationOwner(): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $user->permissions()->sync(
            Permission::whereIn('name', ['tasks.create'])->pluck('id')->all()
        );
        $user->roles()->attach(Role::where('name', 'Vendedor')->firstOrFail()->id);

        return $user->fresh();
    }

    private function makeAutomation(User $owner): Automation
    {
        return Automation::factory()->create([
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'status' => 'active',
            'trigger_type' => 'lead.created',
            'conditions' => [],
            'actions' => [
                [
                    'type' => 'create_task',
                    'title' => 'Seguimiento',
                    'description' => null,
                    'priority' => 'medium',
                    'due_in_days' => 1,
                    'assigned_to_mode' => 'subject_owner',
                    'fixed_user_id' => null,
                ],
            ],
        ]);
    }
}
