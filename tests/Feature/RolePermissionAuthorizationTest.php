<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

test('system_admin can access /admin/roles and view baseline roles', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');

    $this->actingAs($admin)
        ->get(route('admin.roles'))
        ->assertOk()
        ->assertSee('NCAA Roles & Permissions')
        ->assertSee('system_admin')
        ->assertSee('lead_auditor')
        ->assertSee('field_auditor')
        ->assertSee('reviewer')
        ->assertSee('head_of_audit')
        ->assertSee('director')
        ->assertSee('auditee');
});

test('unauthorized users without roles.manage permission cannot access /admin/roles', function () {
    $user = User::factory()->create();
    $user->assignRole('auditee');

    $this->actingAs($user)
        ->get(route('admin.roles'))
        ->assertForbidden();
});

test('admin can create a new custom role with chosen permissions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');

    Livewire::actingAs($admin)
        ->test('pages::admin.roles')
        ->call('openCreateModal')
        ->set('roleName', 'compliance_officer')
        ->set('selectedPermissions', ['processes.view', 'ncrs.view', 'ncrs.raise'])
        ->call('saveRole')
        ->assertHasNoErrors();

    $role = Role::findByName('compliance_officer', 'web');
    expect($role)->not->toBeNull();
    expect($role->hasPermissionTo('processes.view'))->toBeTrue();
    expect($role->hasPermissionTo('ncrs.raise'))->toBeTrue();
    expect($role->hasPermissionTo('audits.schedule'))->toBeFalse();
});

test('a user with a new custom role gets exactly the access granted by their permissions', function () {
    // Create custom role with ONLY ncrs.view and processes.view (no processes.create, no audits.schedule)
    $customRole = Role::create(['name' => 'external_inspector', 'guard_name' => 'web']);
    $customRole->syncPermissions(['ncrs.view', 'processes.view']);

    $user = User::factory()->create();
    $user->assignRole('external_inspector');

    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    // 1. Can view processes list
    $this->actingAs($user)
        ->get(route('processes.index'))
        ->assertOk();

    // 2. Cannot create processes (middleware blocked)
    $this->actingAs($user)
        ->get(route('processes.create'))
        ->assertForbidden();

    // 3. Can view NCRs index
    $this->actingAs($user)
        ->get(route('ncrs.index'))
        ->assertOk();

    // 4. Cannot schedule audits
    $this->actingAs($user)
        ->get(route('audits.index'))
        ->assertForbidden();
});

test('a user can hold more than one role and gets union of their permissions', function () {
    $roleA = Role::create(['name' => 'audits_viewer', 'guard_name' => 'web']);
    $roleA->syncPermissions(['audits.view']);

    $roleB = Role::create(['name' => 'ncrs_manager', 'guard_name' => 'web']);
    $roleB->syncPermissions(['ncrs.view', 'ncrs.raise']);

    $user = User::factory()->create();
    $user->assignRole(['audits_viewer', 'ncrs_manager']);

    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    expect($user->can('audits.view'))->toBeTrue();
    expect($user->can('ncrs.view'))->toBeTrue();
    expect($user->can('ncrs.raise'))->toBeTrue();
    expect($user->can('audits.schedule'))->toBeFalse();

    $this->actingAs($user)
        ->get(route('audits.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('ncrs.index'))
        ->assertOk();
});

test('admin can update role permissions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');

    $role = Role::create(['name' => 'safety_analyst', 'guard_name' => 'web']);
    $role->syncPermissions(['processes.view']);

    Livewire::actingAs($admin)
        ->test('pages::admin.roles')
        ->call('openEditModal', $role->id)
        ->set('selectedPermissions', ['processes.view', 'audits.view', 'caps.review'])
        ->call('saveRole')
        ->assertHasNoErrors();

    $role->refresh();
    expect($role->hasPermissionTo('audits.view'))->toBeTrue();
    expect($role->hasPermissionTo('caps.review'))->toBeTrue();
});
