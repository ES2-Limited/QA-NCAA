<?php

use App\Models\Audit;
use App\Models\AuditApproval;
use App\Models\Certificate;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Process;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkflowStep;
use Database\Seeders\WorkflowStepSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['system_admin', 'lead_auditor', 'field_auditor', 'reviewer', 'head_of_audit', 'director', 'auditee', 'compliance_officer'] as $r) {
        Role::findOrCreate($r);
    }

    Storage::fake('public');
    WorkflowStep::truncate();
    (new WorkflowStepSeeder)->run();
});

function createWorkflowAuditFixture(): array
{
    $dir = Directorate::firstOrCreate(['code' => 'DIR-WF'], ['name' => 'Workflow Directorate']);
    $dept = Department::firstOrCreate(['code' => 'DEPT-WF'], ['directorate_id' => $dir->id, 'name' => 'Workflow Dept']);
    $unit = Unit::firstOrCreate(['code' => 'UNIT-WF'], ['department_id' => $dept->id, 'name' => 'Workflow Unit']);

    $userReviewer = User::factory()->create();
    $userReviewer->assignRole('reviewer');

    $userHead = User::factory()->create();
    $userHead->assignRole('head_of_audit');

    $userDirector = User::factory()->create();
    $userDirector->assignRole('director');

    $userOfficer = User::factory()->create();
    $userOfficer->assignRole('compliance_officer');

    $process = Process::firstOrCreate(['name' => 'WF Test Process'], [
        'directorate_id' => $dir->id,
        'department_id' => $dept->id,
        'unit_id' => $unit->id,
        'type' => 'routine',
        'frequency' => 'monthly',
        'created_by' => $userReviewer->id,
        'status' => 'active',
    ]);

    $audit = Audit::create([
        'reference_number' => 'AUD-WF-'.uniqid(),
        'process_id' => $process->id,
        'title' => 'Configurable Workflow Audit Test',
        'directorate_id' => $dir->id,
        'scheduled_for' => now()->toDateString(),
        'status' => 'submitted',
        'lead_auditor_id' => $userReviewer->id,
        'created_by' => $userReviewer->id,
    ]);

    return compact('dir', 'dept', 'unit', 'userReviewer', 'userHead', 'userDirector', 'userOfficer', 'audit');
}

test('system_admin can configure workflow steps on /admin/workflow', function () {
    $admin = User::where('email', 'admin@ncaa.gov.ng')->first() ?: User::factory()->create();
    if (! $admin->hasRole('system_admin')) {
        $admin->assignRole('system_admin');
    }

    $this->actingAs($admin);

    $this->get(route('admin.workflow'))
        ->assertOk()
        ->assertSee('Configurable Audit Approval Workflow')
        ->assertSee('Technical Quality Review')
        ->assertSee('Head of Audit Executive Endorsement')
        ->assertSee('Directorate Final Certification');

    // Add a new workflow step via Livewire
    Livewire::test('pages::admin.workflow')
        ->set('stepName', 'Independent Compliance Verification')
        ->set('stepRole', 'compliance_officer')
        ->set('stepActionStatus', 'officer_verified')
        ->set('stepCanReturn', true)
        ->set('stepRequired', true)
        ->set('stepIsFinal', false)
        ->call('saveStep')
        ->assertHasNoErrors();

    expect(WorkflowStep::where('name', 'Independent Compliance Verification')->exists())->toBeTrue();
});

test('a 2-step workflow chain works and certificate is generated at step 2', function () {
    $f = createWorkflowAuditFixture();

    // Configure 2-step chain: Reviewer -> Director (final)
    WorkflowStep::truncate();
    WorkflowStep::create([
        'order' => 1,
        'name' => 'QA Review',
        'role' => 'reviewer',
        'action_status' => 'qa_reviewed',
        'can_return' => true,
        'required' => true,
        'is_final' => false,
    ]);
    WorkflowStep::create([
        'order' => 2,
        'name' => 'Director Sign-off',
        'role' => 'director',
        'action_status' => 'director_certified',
        'can_return' => true,
        'required' => true,
        'is_final' => true,
    ]);

    // 1. Step 1 (QA Review by Reviewer)
    Livewire::actingAs($f['userReviewer'])
        ->test('pages::approvals.show', ['audit' => $f['audit']])
        ->set('approvalComment', 'QA checks verified.')
        ->call('approveStep')
        ->assertHasNoErrors();

    expect($f['audit']->fresh()->status)->toBe('qa_reviewed');
    expect(AuditApproval::where('audit_id', $f['audit']->id)->where('action', 'qa_reviewed')->exists())->toBeTrue();

    // 2. Step 2 (Director final certification)
    Livewire::actingAs($f['userDirector'])
        ->test('pages::approvals.show', ['audit' => $f['audit']->fresh()])
        ->set('approvalComment', 'Direct authorization granted.')
        ->call('approveStep')
        ->assertHasNoErrors();

    $freshAudit = $f['audit']->fresh();
    expect($freshAudit->status)->toBe('director_certified');

    // Verify certificate was issued because step 2 is final
    $cert = Certificate::where('audit_id', $freshAudit->id)->first();
    expect($cert)->not->toBeNull();
});

test('a 4-step workflow chain works through all tiers to final certification', function () {
    $f = createWorkflowAuditFixture();

    // Configure 4-step chain
    WorkflowStep::truncate();
    WorkflowStep::create(['order' => 1, 'name' => 'Step 1 - Review', 'role' => 'reviewer', 'action_status' => 's1_done', 'can_return' => true, 'is_final' => false]);
    WorkflowStep::create(['order' => 2, 'name' => 'Step 2 - Compliance', 'role' => 'compliance_officer', 'action_status' => 's2_done', 'can_return' => true, 'is_final' => false]);
    WorkflowStep::create(['order' => 3, 'name' => 'Step 3 - Head', 'role' => 'head_of_audit', 'action_status' => 's3_done', 'can_return' => true, 'is_final' => false]);
    WorkflowStep::create(['order' => 4, 'name' => 'Step 4 - Director', 'role' => 'director', 'action_status' => 's4_done', 'can_return' => true, 'is_final' => true]);

    // Step 1
    Livewire::actingAs($f['userReviewer'])
        ->test('pages::approvals.show', ['audit' => $f['audit']])
        ->call('approveStep')
        ->assertHasNoErrors();
    expect($f['audit']->fresh()->status)->toBe('s1_done');

    // Step 2
    Livewire::actingAs($f['userOfficer'])
        ->test('pages::approvals.show', ['audit' => $f['audit']->fresh()])
        ->call('approveStep')
        ->assertHasNoErrors();
    expect($f['audit']->fresh()->status)->toBe('s2_done');

    // Step 3
    Livewire::actingAs($f['userHead'])
        ->test('pages::approvals.show', ['audit' => $f['audit']->fresh()])
        ->call('approveStep')
        ->assertHasNoErrors();
    expect($f['audit']->fresh()->status)->toBe('s3_done');

    // Step 4 (Final)
    Livewire::actingAs($f['userDirector'])
        ->test('pages::approvals.show', ['audit' => $f['audit']->fresh()])
        ->call('approveStep')
        ->assertHasNoErrors();
    expect($f['audit']->fresh()->status)->toBe('s4_done');

    // Verify certificate issued at step 4
    expect(Certificate::where('audit_id', $f['audit']->id)->exists())->toBeTrue();
});

test('return audit rolls back to the previous step in the configured chain', function () {
    $f = createWorkflowAuditFixture();

    // Chain: Reviewer (s1) -> Head (s2) -> Director (s3)
    WorkflowStep::truncate();
    WorkflowStep::create(['order' => 1, 'name' => 'Reviewer Tier', 'role' => 'reviewer', 'action_status' => 's1_done', 'can_return' => true, 'is_final' => false]);
    WorkflowStep::create(['order' => 2, 'name' => 'Head Tier', 'role' => 'head_of_audit', 'action_status' => 's2_done', 'can_return' => true, 'is_final' => false]);
    WorkflowStep::create(['order' => 3, 'name' => 'Director Tier', 'role' => 'director', 'action_status' => 's3_done', 'can_return' => true, 'is_final' => true]);

    // 1. Advance to step 2 (status becomes s1_done)
    Livewire::actingAs($f['userReviewer'])
        ->test('pages::approvals.show', ['audit' => $f['audit']])
        ->call('approveStep')
        ->assertHasNoErrors();
    expect($f['audit']->fresh()->status)->toBe('s1_done');

    // 2. Advance to step 3 (status becomes s2_done)
    Livewire::actingAs($f['userHead'])
        ->test('pages::approvals.show', ['audit' => $f['audit']->fresh()])
        ->call('approveStep')
        ->assertHasNoErrors();
    expect($f['audit']->fresh()->status)->toBe('s2_done');

    // 3. Director at step 3 returns the audit -> goes back to step 2 status (s1_done)
    Livewire::actingAs($f['userDirector'])
        ->test('pages::approvals.show', ['audit' => $f['audit']->fresh()])
        ->set('returnComment', 'Missing documentation on ramp maintenance.')
        ->call('returnAudit')
        ->assertHasNoErrors();

    expect($f['audit']->fresh()->status)->toBe('s1_done');

    $returnApproval = AuditApproval::where('audit_id', $f['audit']->id)->where('action', 'returned')->latest('id')->first();
    expect($returnApproval)->not->toBeNull();
    expect($returnApproval->comment)->toContain('Missing documentation on ramp maintenance.');
    expect($returnApproval->comment)->toContain('Director Tier');

    // 4. Head at step 2 returns the audit -> goes back to step 1 (first step returns to 'returned')
    Livewire::actingAs($f['userHead'])
        ->test('pages::approvals.show', ['audit' => $f['audit']->fresh()])
        ->set('returnComment', 'Reviewer please re-check calibration records.')
        ->call('returnAudit')
        ->assertHasNoErrors();

    expect($f['audit']->fresh()->status)->toBe('returned');
});
