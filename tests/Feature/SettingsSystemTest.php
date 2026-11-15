<?php

use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Models\Finding;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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

    expect(setting('audit.finding_levels'))->toBeArray();
    expect(count(setting('audit.finding_levels')))->toBe(3);
    expect(setting('audit.finding_levels')[0]['level'])->toBe(1);
    expect(setting('audit.finding_levels')[0]['cap_submission_days'])->toBe(3);
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
        ->assertSee('Audit Rules');

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

test('changing level 1 deadline changes new NCR due dates and custom NCR format is used', function () {
    $reviewer = User::where('email', 'reviewer@ncaa.gov.ng')->first();
    $admin = User::where('email', 'admin@ncaa.gov.ng')->first();
    $audit = Audit::first();
    $answer = AuditAnswer::where('audit_id', $audit->id)->first();

    // 1. Update audit settings as admin: change level 1 deadline to 5 days and NCR format to FINDING-{YYYY}-{####}
    $this->actingAs($admin);

    $levels = [
        ['level' => 1, 'name' => 'Level 1 Critical', 'cap_submission_days' => 5, 'closure_days' => 20],
        ['level' => 2, 'name' => 'Level 2 Major', 'cap_submission_days' => 14, 'closure_days' => 30],
    ];

    Livewire::test('pages::admin.settings')
        ->set('activeTab', 'audit')
        ->set('finding_levels', $levels)
        ->set('reference_format_ncr', 'FINDING-{YYYY}-{####}')
        ->call('saveAuditRules')
        ->assertHasNoErrors();

    expect(setting('audit.finding_levels')[0]['cap_submission_days'])->toBe(5);
    expect(setting('audit.reference_format.ncr'))->toBe('FINDING-{YYYY}-{####}');

    // 2. Now act as Reviewer on the review page and raise a Level 1 NCR
    $this->actingAs($reviewer);

    $expectedDueDate = now()->addDays(5)->toDateString();

    Livewire::test('pages::review.show', ['audit' => $audit])
        ->call('openNcrModal', $answer->id, 'Nig.CARs Part 8.5', 'Non-compliant battery casing observed.')
        ->set('ncrLevel', 1)
        ->set('ncrDescription', 'Non-compliant battery casing observed.')
        ->call('raiseNcr')
        ->assertHasNoErrors();

    // Verify the latest finding uses the custom format and new 5-day deadline
    $ncr = Finding::latest('id')->first();
    expect($ncr)->not->toBeNull();
    expect($ncr->reference)->toStartWith('FINDING-'.date('Y').'-');
    expect($ncr->cap_due_at->toDateString())->toBe($expectedDueDate);
    expect(Carbon::parse($ncr->due_date)->toDateString())->toBe($expectedDueDate);
});
