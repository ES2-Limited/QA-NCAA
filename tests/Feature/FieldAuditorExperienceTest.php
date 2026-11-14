<?php

use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Models\User;
use App\Notifications\AuditSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->artisan('db:seed');
    Storage::fake('public');
});

test('assigned field auditor can open audit, answer and save with lat/lng', function () {
    $auditor = User::where('email', 'auditor@ncaa.gov.ng')->first();
    $audit = Audit::where('reference_number', 'AUD-2026-DSD-001')->first();

    expect($auditor)->not->toBeNull();
    expect($audit)->not->toBeNull();
    expect($audit->auditors->pluck('id'))->toContain($auditor->id);

    $this->actingAs($auditor);

    $this->get(route('field.audit', $audit->id))
        ->assertStatus(200);

    Livewire::test('pages::field.audit', ['audit' => $audit])
        ->call('loadQuestionData', 3)
        ->set('activeValue', 'Compliant')
        ->set('activeComment', 'Continuity resistance test under 0.5 ohms confirmed.')
        ->call('setLocation', 9.006792, 7.263172, 5.0)
        ->call('saveAnswer', false)
        ->assertHasNoErrors();

    $this->assertDatabaseHas('audit_answers', [
        'audit_id' => $audit->id,
        'question_key' => 3,
        'comment' => 'Continuity resistance test under 0.5 ohms confirmed.',
        'lat' => 9.006792,
        'lng' => 7.263172,
        'answered_by' => $auditor->id,
    ]);
});

test('unassigned field auditor gets 403 when opening audit', function () {
    $auditee = User::where('email', 'auditee@ncaa.gov.ng')->first();
    $audit = Audit::where('reference_number', 'AUD-2026-DSD-001')->first();

    // Give auditee role field_auditor to test audit-level authorization
    $auditee->assignRole('field_auditor');

    $this->actingAs($auditee);

    $response = $this->get(route('field.audit', $audit->id));
    $response->assertStatus(403);
});

test('submit is blocked while questions remain unanswered', function () {
    $auditor = User::where('email', 'auditor@ncaa.gov.ng')->first();
    $audit = Audit::where('reference_number', 'AUD-2026-DSD-001')->first();

    $this->actingAs($auditor);

    // Question 3 and 4 are still unanswered in the initial seed
    Livewire::test('pages::field.audit', ['audit' => $audit])
        ->call('submitAudit')
        ->assertSee('Cannot submit audit');

    $audit->refresh();
    expect($audit->status)->toBe('in_progress');
});

test('submit notifies the reviewer, stamps submitted_at and locks the audit', function () {
    Notification::fake();

    $auditor = User::where('email', 'auditor@ncaa.gov.ng')->first();
    $audit = Audit::where('reference_number', 'AUD-2026-DSD-001')->first();
    $reviewer = $audit->reviewer;

    expect($reviewer)->not->toBeNull();
    $this->actingAs($auditor);

    // Answer the remaining 2 questions (indices 3 and 4)
    AuditAnswer::updateOrCreate(
        ['audit_id' => $audit->id, 'question_key' => 3],
        ['value' => 'Compliant', 'answered_at' => now(), 'answered_by' => $auditor->id]
    );

    AuditAnswer::updateOrCreate(
        ['audit_id' => $audit->id, 'question_key' => 4],
        ['value' => 'Ramp markings clearly visible and gate 4 cleared.', 'answered_at' => now(), 'answered_by' => $auditor->id]
    );

    Livewire::test('pages::field.audit', ['audit' => $audit])
        ->call('submitAudit')
        ->assertHasNoErrors()
        ->assertSee('Audit successfully submitted');

    $audit->refresh();
    expect($audit->status)->toBe('submitted');
    expect($audit->submitted_at)->not->toBeNull();

    Notification::assertSentTo($reviewer, AuditSubmitted::class);
});

test('evidence upload is stored and linked polymorphically to audit answer', function () {
    $auditor = User::where('email', 'auditor@ncaa.gov.ng')->first();
    $audit = Audit::where('reference_number', 'AUD-2026-DSD-001')->first();

    $this->actingAs($auditor);

    $photo = UploadedFile::fake()->image('fuel_bowser_meter.jpg', 600, 400);

    Livewire::test('pages::field.audit', ['audit' => $audit])
        ->call('loadQuestionData', 0)
        ->set('newPhotos', [$photo])
        ->call('saveAnswer', false)
        ->assertHasNoErrors();

    $answer = AuditAnswer::where('audit_id', $audit->id)->where('question_key', 0)->first();
    expect($answer->attachments)->not->toBeEmpty();

    $uploaded = $answer->attachments->where('original_name', 'fuel_bowser_meter.jpg')->first();
    expect($uploaded)->not->toBeNull();
    expect(Storage::disk('public')->exists($uploaded->path))->toBeTrue();
    expect($uploaded->mime_type)->toContain('image');
});

test('field_auditor lands on /field after login', function () {
    $auditor = User::where('email', 'auditor@ncaa.gov.ng')->first();
    $this->actingAs($auditor);

    $this->get('/')->assertRedirect(route('field.index'));
    $this->get('/dashboard')->assertRedirect(route('field.index'));
});
