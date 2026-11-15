<?php

use App\Models\Audit;
use App\Models\Directorate;
use App\Models\Document;
use App\Models\Lookup;
use App\Models\Process;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function createLookupTestAdmin(): User
{
    Role::firstOrCreate(['name' => 'system_admin']);
    Role::firstOrCreate(['name' => 'lead_auditor']);

    $user = User::factory()->create([
        'email' => 'admin_lookups_'.uniqid().'@ncaa.gov.ng',
    ]);
    $user->assignRole('system_admin');

    return $user;
}

test('admin can access /admin/lookups and view lookup lists', function () {
    $admin = createLookupTestAdmin();

    $this->actingAs($admin)
        ->get(route('admin.lookups'))
        ->assertOk()
        ->assertSee('NCAA Lookup Lists')
        ->assertSee('Process Categories')
        ->assertSee('Document Types')
        ->assertSee('Audit Statuses')
        ->assertSee('NCR Statuses');
});

test('admin can add, update and toggle active on process categories', function () {
    $admin = createLookupTestAdmin();

    Livewire::actingAs($admin)
        ->test('pages::admin.lookups')
        ->call('setTab', 'process_category')
        ->set('formName', 'Aerodrome Safety')
        ->set('formColour', 'amber')
        ->set('formSortOrder', 10)
        ->set('formIsActive', true)
        ->call('saveLookup')
        ->assertHasNoErrors();

    $lookup = Lookup::where('type', 'process_category')->where('name', 'Aerodrome Safety')->first();
    expect($lookup)->not->toBeNull();
    expect($lookup->colour)->toBe('amber');
    expect($lookup->key)->toBe('aerodrome_safety');
    expect($lookup->is_active)->toBeTrue();

    // Toggle active flag
    Livewire::actingAs($admin)
        ->test('pages::admin.lookups')
        ->call('toggleActive', $lookup->id);

    expect($lookup->fresh()->is_active)->toBeFalse();
});

test('admin can add and edit document types', function () {
    $admin = createLookupTestAdmin();

    Livewire::actingAs($admin)
        ->test('pages::admin.lookups')
        ->call('setTab', 'document_type')
        ->set('formName', 'Airworthiness Directive')
        ->set('formColour', 'rose')
        ->set('formSortOrder', 5)
        ->set('formIsActive', true)
        ->call('saveLookup')
        ->assertHasNoErrors();

    $lookup = Lookup::where('type', 'document_type')->where('name', 'Airworthiness Directive')->first();
    expect($lookup)->not->toBeNull();
    expect($lookup->colour)->toBe('rose');

    // Edit item
    Livewire::actingAs($admin)
        ->test('pages::admin.lookups')
        ->call('openEditModal', $lookup->id)
        ->set('formName', 'Mandatory Airworthiness Directive')
        ->set('formColour', 'purple')
        ->call('saveLookup')
        ->assertHasNoErrors();

    expect($lookup->fresh()->name)->toBe('Mandatory Airworthiness Directive');
    expect($lookup->fresh()->colour)->toBe('purple');
});

test('process wizard provides categories from lookups and process list filters by category', function () {
    $admin = createLookupTestAdmin();
    $admin->assignRole('lead_auditor');

    $dir = Directorate::firstOrCreate(['code' => 'TEST_DIR'], ['name' => 'Testing Directorate']);

    // Ensure category exists in lookups
    Lookup::updateOrCreate(
        ['type' => 'process_category', 'key' => 'licensing'],
        ['name' => 'Licensing', 'colour' => 'blue', 'sort_order' => 1, 'is_active' => true]
    );
    Lookup::updateOrCreate(
        ['type' => 'process_category', 'key' => 'surveillance'],
        ['name' => 'Surveillance', 'colour' => 'emerald', 'sort_order' => 2, 'is_active' => true]
    );

    // Create processes with categories
    $p1 = Process::create([
        'name' => 'Licensing Exam Review',
        'category' => 'licensing',
        'directorate_id' => $dir->id,
        'type' => 'routine',
        'frequency' => 'monthly',
        'status' => 'active',
        'created_by' => $admin->id,
    ]);

    $p2 = Process::create([
        'name' => 'Ramp Surveillance Protocol',
        'category' => 'surveillance',
        'directorate_id' => $dir->id,
        'type' => 'routine',
        'frequency' => 'monthly',
        'status' => 'active',
        'created_by' => $admin->id,
    ]);

    // Test process index filtering by category
    Livewire::actingAs($admin)
        ->test('pages::processes.index')
        ->assertSee('Licensing Exam Review')
        ->assertSee('Ramp Surveillance Protocol')
        ->set('filterCategory', 'licensing')
        ->assertSee('Licensing Exam Review')
        ->assertDontSee('Ramp Surveillance Protocol');

    // Test process form wizard contains the categories
    Livewire::actingAs($admin)
        ->test('pages::processes.form')
        ->assertSee('Licensing')
        ->assertSee('Surveillance');
});

test('document upload supports document type select from lookups', function () {
    $admin = createLookupTestAdmin();
    Storage::fake('local');

    Lookup::updateOrCreate(
        ['type' => 'document_type', 'key' => 'regulation'],
        ['name' => 'Regulation', 'colour' => 'amber', 'sort_order' => 1, 'is_active' => true]
    );

    $file = UploadedFile::fake()->create('ncaa_reg_2026.pdf', 500, 'application/pdf');

    Livewire::actingAs($admin)
        ->test('pages::admin.documents')
        ->call('openUploadModal')
        ->set('title', 'NCAA Civil Aviation Regulations Part 8')
        ->set('type', 'regulation')
        ->set('file', $file)
        ->call('uploadDocument')
        ->assertHasNoErrors();

    $doc = Document::where('title', 'NCAA Civil Aviation Regulations Part 8')->first();
    expect($doc)->not->toBeNull();
    expect($doc->type)->toBe('regulation');
});

test('status badge component resolves labels and colours from lookups for audit and ncr statuses', function () {
    // Customise audit status in lookups
    Lookup::updateOrCreate(
        ['type' => 'audit_status', 'key' => 'scheduled'],
        ['name' => 'Planned Audit', 'colour' => 'cyan', 'sort_order' => 1, 'is_active' => true]
    );

    Lookup::updateOrCreate(
        ['type' => 'ncr_status', 'key' => 'open'],
        ['name' => 'Critical Finding Active', 'colour' => 'purple', 'sort_order' => 1, 'is_active' => true]
    );

    $auditStatus = Lookup::resolveStatus('audit_status', 'scheduled');
    expect($auditStatus['label'])->toBe('Planned Audit');
    expect($auditStatus['colour'])->toBe('cyan');

    $ncrStatus = Lookup::resolveStatus('ncr_status', 'open');
    expect($ncrStatus['label'])->toBe('Critical Finding Active');
    expect($ncrStatus['colour'])->toBe('purple');
});
