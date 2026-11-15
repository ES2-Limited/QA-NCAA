<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->artisan('db:seed');
    Storage::fake('public');
});

test('setting helper and service store and retrieve values with caching and clear on save', function () {
    $service = app(SettingService::class);

    // Initial value from seed
    expect(setting('general.system_name'))->toBe('Nigeria Civil Aviation Authority — DSD QA/IA System');

    // Setting a new value updates the database and clears the cache
    setting(['general.system_name' => 'Custom NCAA Aviation System']);

    expect(setting('general.system_name'))->toBe('Custom NCAA Aviation System');
    expect(Setting::where('key', 'general.system_name')->value('value'))->toBe('Custom NCAA Aviation System');

    // Audit policy setting
    expect(setting('audit.cap_due.level_1'))->toBe(7);
});

test('non-admin users cannot access admin settings page', function () {
    $auditor = User::where('email', 'auditor@ncaa.gov.ng')->first();
    expect($auditor)->not->toBeNull();

    $this->actingAs($auditor);

    $response = $this->get(route('admin.settings'));
    // Enforced mobile layout or role gate prevents access
    expect(in_array($response->status(), [302, 403]))->toBeTrue();
});

test('system_admin can access admin settings page and save general branding', function () {
    $admin = User::where('email', 'admin@ncaa.gov.ng')->first();
    expect($admin)->not->toBeNull();

    $this->actingAs($admin);

    $this->get(route('admin.settings'))->assertOk()
        ->assertSee('System Settings & Configuration')
        ->assertSee('General & Branding')
        ->assertSee('Audit & CAP Policies');

    // Test updating settings via Livewire
    Livewire::test('pages::admin.settings')
        ->set('system_name', 'SkyOps QA Platform')
        ->set('organisation_name', 'National Sky Authority')
        ->set('primary_color', '#123456')
        ->set('accent_color', '#654321')
        ->set('support_email', 'help@skyops.gov')
        ->set('timezone', 'Africa/Lagos')
        ->set('date_format', 'Y-m-d')
        ->call('saveGeneral')
        ->assertHasNoErrors();

    expect(setting('general.system_name'))->toBe('SkyOps QA Platform');
    expect(setting('general.organisation_name'))->toBe('National Sky Authority');
    expect(setting('general.primary_color'))->toBe('#123456');
    expect(setting('general.accent_color'))->toBe('#654321');

    // Verify UI reflects the change on dashboard
    $res = $this->get(route('dashboard'));
    $res->assertSee('SkyOps QA Platform');
    $res->assertSee('#123456');
    $res->assertSee('#654321');
});

test('system_admin can upload a new logo in admin settings', function () {
    $admin = User::where('email', 'admin@ncaa.gov.ng')->first();
    $this->actingAs($admin);

    $fakeLogo = UploadedFile::fake()->image('custom-logo.png', 200, 200);

    Livewire::test('pages::admin.settings')
        ->set('new_logo', $fakeLogo)
        ->call('saveGeneral')
        ->assertHasNoErrors();

    $logoUrl = setting('general.logo_url');
    expect($logoUrl)->toStartWith('/storage/branding/');

    // Assert file exists on the fake public disk
    $storedPath = str_replace('/storage/', '', $logoUrl);
    Storage::disk('public')->assertExists($storedPath);
});

test('system_admin can reset settings to defaults', function () {
    $admin = User::where('email', 'admin@ncaa.gov.ng')->first();
    $this->actingAs($admin);

    // Modify a setting
    setting(['general.system_name' => 'Temporary Modified Name']);
    expect(setting('general.system_name'))->toBe('Temporary Modified Name');

    // Reset via Livewire
    Livewire::test('pages::admin.settings')
        ->call('resetToDefault', 'general');

    expect(setting('general.system_name'))->toBe('Nigeria Civil Aviation Authority — DSD QA/IA System');
});
