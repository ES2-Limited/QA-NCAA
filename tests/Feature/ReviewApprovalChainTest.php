<?php

use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Models\AuditApproval;
use App\Models\Certificate;
use App\Models\CorrectiveActionPlan;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Finding;
use App\Models\Process;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\CapReviewed;
use App\Notifications\CapSubmitted;
use App\Notifications\NcrRaised;
use App\Services\CertificateGenerator;
use Database\Seeders\WorkflowStepSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['system_admin', 'lead_auditor', 'field_auditor', 'reviewer', 'head_of_audit', 'director', 'auditee'] as $r) {
        Role::findOrCreate($r);
    }

    (new WorkflowStepSeeder)->run();

    (new \Database\Seeders\WorkflowStepSeeder)->run();
});

function createReviewChainFixture(): array
{
    $dir = Directorate::firstOrCreate(['code' => 'TEST-DIR'], ['name' => 'Test Directorate']);
    $dept = Department::firstOrCreate(['code' => 'TEST-DEPT'], ['directorate_id' => $dir->id, 'name' => 'Test Dept']);
    $unit = Unit::firstOrCreate(['code' => 'TEST-UNIT'], ['department_id' => $dept->id, 'name' => 'Test Unit']);

    $reviewer = User::factory()->create(['unit_id' => $unit->id]);
    $reviewer->assignRole('reviewer');

    $fieldAuditor = User::factory()->create(['unit_id' => $unit->id]);
    $fieldAuditor->assignRole('field_auditor');

    $auditee = User::factory()->create(['unit_id' => $unit->id]);
    $auditee->assignRole('auditee');

    $headOfAudit = User::factory()->create();
    $headOfAudit->assignRole('head_of_audit');

    $director = User::factory()->create();
    $director->assignRole('director');

    $process = Process::firstOrCreate(['name' => 'Ramp QA Test Process'], [
        'directorate_id' => $dir->id,
        'department_id' => $dept->id,
        'unit_id' => $unit->id,
        'type' => 'routine',
        'frequency' => 'monthly',
        'created_by' => $reviewer->id,
        'status' => 'active',
    ]);

    $audit = Audit::create([
        'reference_number' => 'AUD-TEST-'.uniqid(),
        'process_id' => $process->id,
        'title' => 'Test Review & Approval Audit',
        'directorate_id' => $dir->id,
        'department_id' => $dept->id,
        'unit_id' => $unit->id,
        'scheduled_for' => now()->toDateString(),
        'status' => 'submitted',
        'lead_auditor_id' => $reviewer->id,
        'reviewer_id' => $reviewer->id,
        'created_by' => $reviewer->id,
        'questions_snapshot' => [
            [
                'order' => 1,
                'question' => 'Are ramp safety chocks properly placed?',
                'type' => 'radio',
                'options' => ['Yes', 'No', 'N/A'],
                'requirement_ref' => 'NIG.CARs.Part12.045',
                'guidance' => 'Inspect wheels for chocks on both sides.',
            ],
            [
                'order' => 2,
                'question' => 'Are fire extinguishers in date?',
                'type' => 'radio',
                'options' => ['Yes', 'No'],
                'requirement_ref' => 'NIG.CARs.Part12.099',
            ],
        ],
    ]);

    $audit->auditors()->attach($fieldAuditor->id);

    $answer1 = AuditAnswer::create([
        'audit_id' => $audit->id,
        'question_key' => '0',
        'value' => 'No',
        'comment' => 'Left gear chocks missing during turnaround.',
        'answered_at' => now(),
        'answered_by' => $fieldAuditor->id,
        'lat' => 6.5774,
        'lng' => 3.3211,
    ]);

    $answer2 = AuditAnswer::create([
        'audit_id' => $audit->id,
        'question_key' => '1',
        'value' => 'Yes',
        'comment' => 'All extinguisher inspection tags valid.',
        'answered_at' => now(),
        'answered_by' => $fieldAuditor->id,
    ]);

    return compact('dir', 'dept', 'unit', 'reviewer', 'fieldAuditor', 'auditee', 'headOfAudit', 'director', 'process', 'audit', 'answer1', 'answer2');
}

test('reviewer can view audit answers with GPS map link', function () {
    $f = createReviewChainFixture();

    $this->actingAs($f['reviewer'])
        ->get(route('review.show', $f['audit']))
        ->assertOk()
        ->assertSee('Are ramp safety chocks properly placed?')
        ->assertSee('Left gear chocks missing during turnaround.')
        ->assertSee('openstreetmap.org/?mlat=6.5774', false);
});

test('reviewer flags answer as non-compliant raising NCR with SLA due dates', function () {
    Notification::fake();
    $f = createReviewChainFixture();

    // Level 1: cap_due_at +72 hours
    Livewire::actingAs($f['reviewer'])
        ->test('pages::review.show', ['audit' => $f['audit']])
        ->call('openNcrModal', $f['answer1']->id)
        ->set('ncrLevel', 1)
        ->set('ncrDescription', 'Critical safety non-conformance: wheel chocks absent.')
        ->set('ncrRequirementRef', 'NIG.CARs.Part12.045')
        ->set('ncrAssignedUnitId', $f['unit']->id)
        ->call('raiseNcr')
        ->assertHasNoErrors();

    $ncr = Finding::where('audit_id', $f['audit']->id)->first();
    expect($ncr)->not->toBeNull()
        ->and($ncr->level)->toBe(1)
        ->and($ncr->status)->toBe('open')
        ->and($ncr->reference)->toStartWith('NCR-')
        ->and($ncr->cap_due_at->diffInHours(now()->addHours(72)))->toBeLessThan(2);

    Notification::assertSentTo($f['auditee'], NcrRaised::class);

    // Level 2: cap_due_at +14 days
    Livewire::actingAs($f['reviewer'])
        ->test('pages::review.show', ['audit' => $f['audit']])
        ->call('openNcrModal', $f['answer2']->id)
        ->set('ncrLevel', 2)
        ->set('ncrDescription', 'Minor record keeping lapse.')
        ->set('ncrAssignedUnitId', $f['unit']->id)
        ->call('raiseNcr')
        ->assertHasNoErrors();

    $ncr2 = Finding::where('audit_id', $f['audit']->id)->where('level', 2)->first();
    expect($ncr2->cap_due_at->diffInDays(now()->addDays(14)))->toBeLessThan(2);
});

test('reviewer can return audit to field auditor with return reason', function () {
    $f = createReviewChainFixture();

    Livewire::actingAs($f['reviewer'])
        ->test('pages::review.show', ['audit' => $f['audit']])
        ->set('returnComment', 'Evidence photo for item 1 is blurry, please retake.')
        ->call('returnToFieldAuditor')
        ->assertHasNoErrors();

    expect($f['audit']->fresh()->status)->toBe('returned');

    // Field auditor can now view it in returned/editable state
    $this->actingAs($f['fieldAuditor'])
        ->get(route('field.audit', $f['audit']))
        ->assertOk()
        ->assertSee('Audit Returned by Reviewer');
});

test('reviewer can forward audit to Head of Audit', function () {
    $f = createReviewChainFixture();

    Livewire::actingAs($f['reviewer'])
        ->test('pages::review.show', ['audit' => $f['audit']])
        ->call('forwardToHeadOfAudit')
        ->assertHasNoErrors();

    expect($f['audit']->fresh()->status)->toBe('reviewed');

    $approval = AuditApproval::where('audit_id', $f['audit']->id)->where('action', 'reviewed')->first();
    expect($approval)->not->toBeNull()
        ->and($approval->role)->toBe('reviewer');
});

test('auditee submits CAP with closure evidence and reviewer accepts it to close NCR', function () {
    Notification::fake();
    Storage::fake('public');
    $f = createReviewChainFixture();

    $ncr = Finding::create([
        'audit_id' => $f['audit']->id,
        'audit_answer_id' => $f['answer1']->id,
        'reference' => 'NCR-2026-9999',
        'level' => 1,
        'description' => 'Wheel chocks absent.',
        'requirement_ref' => 'NIG.CARs.Part12.045',
        'assigned_unit_id' => $f['unit']->id,
        'raised_by' => $f['reviewer']->id,
        'cap_due_at' => now()->addHours(72),
        'status' => 'open',
    ]);

    // Auditee views and submits CAP
    Livewire::actingAs($f['auditee'])
        ->test('pages::ncrs.show', ['ncr' => $ncr])
        ->set('proposedAction', 'Procured 10 new pairs of heavy-duty ramp chocks and briefed marshallers.')
        ->set('rootCause', 'Defective chocks discarded without immediate replenishment.')
        ->set('targetDate', now()->addDays(5)->toDateString())
        ->set('closureEvidence', [
            UploadedFile::fake()->create('procurement_receipt.pdf', 100, 'application/pdf'),
        ])
        ->call('submitCap')
        ->assertHasNoErrors();

    $cap = CorrectiveActionPlan::where('ncr_id', $ncr->id)->first();
    expect($cap)->not->toBeNull()
        ->and($cap->status)->toBe('submitted')
        ->and($cap->attachments)->toHaveCount(1)
        ->and($ncr->fresh()->status)->toBe('cap_submitted');

    Notification::assertSentTo($f['reviewer'], CapSubmitted::class);

    // Reviewer reviews and accepts CAP
    Livewire::actingAs($f['reviewer'])
        ->test('pages::ncrs.show', ['ncr' => $ncr->fresh()])
        ->set('reviewerComment', 'Evidence reviewed and satisfactory. Corrective action accepted.')
        ->call('acceptCap')
        ->call('closeNcr')
        ->assertHasNoErrors();

    expect($cap->fresh()->status)->toBe('accepted')
        ->and($ncr->fresh()->status)->toBe('closed');

    Notification::assertSentTo($f['auditee'], CapReviewed::class);
});

test('approval chain: Head endorses and Director certifies generating signed PDF and QR code', function () {
    Storage::fake('public');
    $f = createReviewChainFixture();
    $f['audit']->update(['status' => 'reviewed']);

    // Head of Audit endorses
    Livewire::actingAs($f['headOfAudit'])
        ->test('pages::approvals.show', ['audit' => $f['audit']])
        ->call('generateAiSummary')
        ->set('approvalComment', 'Comprehensive review conducted. All items verified.')
        ->call('headApprove')
        ->assertHasNoErrors();

    expect($f['audit']->fresh()->status)->toBe('head_approved')
        ->and($f['audit']->fresh()->ai_summary)->not->toBeNull();

    $headApproval = AuditApproval::where('audit_id', $f['audit']->id)->where('action', 'head_approved')->first();
    expect($headApproval)->not->toBeNull()
        ->and($headApproval->role)->toBe('head_of_audit');

    // Director final approval & certificate issuance
    Livewire::actingAs($f['director'])
        ->test('pages::approvals.show', ['audit' => $f['audit']->fresh()])
        ->set('approvalComment', 'Compliance confirmed. Certified for operations.')
        ->call('directorApprove')
        ->assertHasNoErrors();

    $audit = $f['audit']->fresh();
    expect($audit->status)->toBe('director_approved');

    $directorApproval = AuditApproval::where('audit_id', $audit->id)->where('action', 'director_approved')->first();
    expect($directorApproval)->not->toBeNull()
        ->and($directorApproval->role)->toBe('director');

    // Certificate verification
    $certificate = Certificate::where('audit_id', $audit->id)->first();
    expect($certificate)->not->toBeNull()
        ->and($certificate->number)->toContain('QA-')
        ->and($certificate->verify_token)->toHaveLength(32);

    Storage::disk('public')->assertExists($certificate->file_path);

    // Public QR verification endpoint (accessible without auth)
    $this->get('/verify/'.$certificate->verify_token)
        ->assertOk()
        ->assertSee('Authentic NCAA Certificate')
        ->assertSee('VALID &amp; CERTIFIED', false)
        ->assertSee($certificate->number);
});

test('access controls protect review and approvals routes from unauthorized roles', function () {
    $f = createReviewChainFixture();

    // Field auditor cannot access review or approvals (redirected to field mobile)
    $this->actingAs($f['fieldAuditor'])
        ->get(route('review.index'))
        ->assertRedirect(route('field.index'));

    $this->actingAs($f['fieldAuditor'])
        ->get(route('approvals.index'))
        ->assertRedirect(route('field.index'));

    // Reviewer only sees audits assigned to them
    $otherReviewer = User::factory()->create();
    $otherReviewer->assignRole('reviewer');

    $this->actingAs($otherReviewer)
        ->get(route('review.show', $f['audit']))
        ->assertForbidden();

    // Public verification endpoint is accessible guest
    $certificate = (new CertificateGenerator)->generate($f['audit']);
    $this->get('/verify/'.$certificate->verify_token)
        ->assertOk()
        ->assertSee($certificate->number);
});
