<?php

use App\Models\Audit;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->artisan('db:seed');
});

test('user with only field_auditor role visiting /settings/profile is redirected to /field/profile', function () {
    $auditor = User::where('email', 'auditor@ncaa.gov.ng')->first();
    expect($auditor)->not->toBeNull();
    expect($auditor->hasRole('field_auditor'))->toBeTrue();
    expect($auditor->roles()->count())->toBe(1);

    $this->actingAs($auditor);

    $response = $this->get('/settings/profile');
    $response->assertRedirect(route('field.profile'));
});

test('user with only field_auditor role visiting /dashboard is redirected to /field', function () {
    $auditor = User::where('email', 'auditor@ncaa.gov.ng')->first();
    $this->actingAs($auditor);

    $response = $this->get('/dashboard');
    $response->assertRedirect(route('field.index'));
});

test('user with only field_auditor role visiting other web routes is redirected to /field', function () {
    $auditor = User::where('email', 'auditor@ncaa.gov.ng')->first();
    $this->actingAs($auditor);

    $this->get('/audits')->assertRedirect(route('field.index'));
    $this->get('/processes')->assertRedirect(route('field.index'));
    $this->get('/documents')->assertRedirect(route('field.index'));
    $this->get('/users')->assertRedirect(route('field.index'));
});

test('every page a field auditor can reach renders the mobile layout', function () {
    $auditor = User::where('email', 'auditor@ncaa.gov.ng')->first();
    $audit = Audit::where('reference_number', 'AUD-2026-DSD-001')->first();
    $this->actingAs($auditor);

    // 1. /field
    $resIndex = $this->get(route('field.index'));
    $resIndex->assertStatus(200);
    $resIndex->assertSee('Field Auditor');
    $resIndex->assertSee('My Audits');
    $resIndex->assertSee('Profile');

    // 2. /field/audits/{id}
    $resAudit = $this->get(route('field.audit', $audit->id));
    $resAudit->assertStatus(200);
    $resAudit->assertSee('Field Auditor');
    $resAudit->assertSee('My Audits');

    // 3. /field/profile
    $resProfile = $this->get(route('field.profile'));
    $resProfile->assertStatus(200);
    $resProfile->assertSee('Auditor Profile');
    $resProfile->assertSee('Change Password');
    $resProfile->assertSee('Field Auditor');

    // 4. /field/notifications
    $resNotif = $this->get(route('field.notifications'));
    $resNotif->assertStatus(200);
    $resNotif->assertSee('Alerts & Notifications');
    $resNotif->assertSee('Field Auditor');
});

test('field auditor can update their password and log out from the mobile profile', function () {
    $auditor = User::where('email', 'auditor@ncaa.gov.ng')->first();
    $this->actingAs($auditor);

    Livewire::test('pages::field.profile')
        ->set('current_password', 'password')
        ->set('password', 'NewSecret123!')
        ->set('password_confirmation', 'NewSecret123!')
        ->call('updatePassword')
        ->assertHasNoErrors();

    $auditor->refresh();
    expect(Hash::check('NewSecret123!', $auditor->password))->toBeTrue();

    // Test logout action redirects to '/' which redirects unauthenticated guests to '/login'
    $component = Livewire::test('pages::field.profile')
        ->call('logout');

    $component->assertRedirect('/');

    $this->assertGuest();
    $this->get('/')->assertRedirect(route('login'));
});
