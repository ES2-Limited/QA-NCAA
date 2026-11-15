<?php

use App\Models\Audit;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Document;
use App\Models\Finding;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('auditee from another unit cannot view an NCR of a different unit', function () {
    // Directorate & Department
    $dir1 = Directorate::first();
    $dept1 = Department::where('directorate_id', $dir1->id)->first();

    // Two distinct units
    $unit1 = Unit::create(['name' => 'Security Screening Unit', 'code' => 'SSU-SEC', 'department_id' => $dept1->id]);
    $unit2 = Unit::create(['name' => 'Air Traffic Unit', 'code' => 'ATU-SEC', 'department_id' => $dept1->id]);

    // Auditee user belonging strictly to unit2
    $foreignAuditee = User::factory()->create([
        'unit_id' => $unit2->id,
        'directorate_id' => null,
    ]);
    $foreignAuditee->assignRole('auditee');

    // Finding belonging to unit1
    $finding = Finding::where('status', 'open')->firstOrFail();
    $finding->assigned_unit_id = $unit1->id;
    // ensure audit belongs to a different directorate or no matching directorate
    if ($finding->audit) {
        $otherDir = Directorate::where('id', '!=', $dir1->id)->first() ?? $dir1;
        $finding->audit->update(['directorate_id' => $otherDir->id]);
    }
    $finding->save();

    // Foreign auditee attempting to view finding belonging to unit1
    Livewire::actingAs($foreignAuditee)
        ->test('pages::ncrs.show', ['ncr' => $finding])
        ->assertForbidden();
});

test('field auditor cannot conduct an audit they are not assigned to', function () {
    // Unassigned field auditor
    $unassignedAuditor = User::factory()->create();
    $unassignedAuditor->assignRole('field_auditor');

    // Find any audit where this auditor is not lead nor in auditors relationship
    $audit = Audit::firstOrFail();
    $audit->update(['lead_auditor_id' => User::where('email', 'lead_auditor@ncaa.gov.ng')->value('id')]);
    $audit->auditors()->detach($unassignedAuditor->id);

    Livewire::actingAs($unassignedAuditor)
        ->test('pages::field.audit', ['audit' => $audit])
        ->assertForbidden();
});

test('auditee without permissions cannot download documents from admin document library', function () {
    Storage::fake('local');

    $doc = Document::create([
        'title' => 'Confidential Regulatory Manual',
        'file_path' => 'documents/manual.pdf',
        'original_name' => 'manual.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
        'uploaded_by' => 1,
    ]);
    Storage::disk('local')->put('documents/manual.pdf', 'dummy content');

    $auditee = User::where('email', 'auditee@ncaa.gov.ng')->firstOrFail();

    $response = $this->actingAs($auditee)
        ->get(route('admin.documents.download', $doc->id));

    $response->assertForbidden();
});

test('tampering with locked audit model id in Livewire component is rejected', function () {
    $admin = User::where('email', 'admin@ncaa.gov.ng')->firstOrFail();
    $audit1 = Audit::firstOrFail();

    expect(fn () => Livewire::actingAs($admin)
        ->test('pages::audits.show', ['audit' => $audit1])
        ->updateProperty('audit', 9999)
    )->toThrow(CannotUpdateLockedPropertyException::class);
});

test('tampering with locked NCR model id in Livewire component is rejected', function () {
    $admin = User::where('email', 'admin@ncaa.gov.ng')->firstOrFail();
    $ncr1 = Finding::firstOrFail();

    expect(fn () => Livewire::actingAs($admin)
        ->test('pages::ncrs.show', ['ncr' => $ncr1])
        ->updateProperty('ncr', 9999)
    )->toThrow(CannotUpdateLockedPropertyException::class);
});
