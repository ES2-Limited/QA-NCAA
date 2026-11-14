<?php

use App\Models\Audit;
use App\Models\Process;
use App\Models\User;
use App\Notifications\AuditAssigned;
use App\Notifications\RoutineAuditDue;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->artisan('db:seed');
});

test('scheduling creates the audit, snapshots questions and notifies the team', function () {
    Notification::fake();

    $leadAuditor = User::where('email', 'lead_auditor@ncaa.gov.ng')->first();
    $reviewer = User::where('email', 'reviewer@ncaa.gov.ng')->first();
    $fieldAuditor = User::where('email', 'auditor@ncaa.gov.ng')->first();
    $process = Process::where('name', 'AMO Internal QA Audit')->first();

    expect($leadAuditor)->not->toBeNull();
    expect($reviewer)->not->toBeNull();
    expect($fieldAuditor)->not->toBeNull();
    expect($process)->not->toBeNull();

    $this->actingAs($leadAuditor);

    $test = Livewire::test('pages::processes.show', ['process' => $process])
        ->set('auditTitle', 'AMO Winter Quality Inspection 2026')
        ->set('scheduledFor', now()->addDays(5)->format('Y-m-d'))
        ->set('reviewerId', (string) $reviewer->id)
        ->set('assignedAuditorIds', [(string) $fieldAuditor->id])
        ->call('scheduleAudit')
        ->assertHasNoErrors();

    $audit = Audit::where('title', 'AMO Winter Quality Inspection 2026')->first();
    expect($audit)->not->toBeNull();
    expect($audit->status)->toBe('scheduled');
    expect($audit->reviewer_id)->toBe($reviewer->id);
    expect($audit->auditors)->toHaveCount(1);
    expect($audit->auditors->first()->id)->toBe($fieldAuditor->id);

    // Assert questions were snapshotted
    expect($audit->questions_snapshot)->toBeArray();
    expect(count($audit->questions_snapshot))->toBe($process->questions()->count());
    expect($audit->questions_snapshot[0]['question'])->toBe($process->questions()->orderBy('order')->first()->question);

    // Notifications sent to team
    Notification::assertSentTo($fieldAuditor, AuditAssigned::class);
    Notification::assertSentTo($reviewer, AuditAssigned::class);
});

test('routine-alerts sends 14-day and 7-day advance alerts', function () {
    Notification::fake();

    $leadAuditor = User::where('email', 'lead_auditor@ncaa.gov.ng')->first();
    $process = Process::where('name', 'AMO Internal QA Audit')->first();

    // 1. Test 14 days away
    $process->update([
        'next_due_at' => now()->addDays(14),
        'created_by' => $leadAuditor->id,
    ]);

    $this->artisan('audits:routine-alerts')->assertSuccessful();
    Notification::assertSentTo($leadAuditor, RoutineAuditDue::class, function ($notification) use ($process) {
        return $notification->daysUntilDue === 14 && $notification->process->id === $process->id;
    });

    // 2. Test 7 days away
    $process->update([
        'next_due_at' => now()->addDays(7),
    ]);

    $this->artisan('audits:routine-alerts')->assertSuccessful();
    Notification::assertSentTo($leadAuditor, RoutineAuditDue::class, function ($notification) use ($process) {
        return $notification->daysUntilDue === 7 && $notification->process->id === $process->id;
    });
});

test('overdue routine processes auto-create one audit and roll next_due_at forward', function () {
    $process = Process::where('name', 'AMO Internal QA Audit')->first();
    $initialDue = now()->subDays(2)->startOfDay();

    $process->update([
        'next_due_at' => $initialDue,
        'frequency' => 'monthly',
    ]);

    $initialAuditCount = Audit::where('process_id', $process->id)->count();

    $this->artisan('audits:routine-alerts')->assertSuccessful();

    // Assert 1 new audit was created
    expect(Audit::where('process_id', $process->id)->count())->toBe($initialAuditCount + 1);

    // Assert next_due_at has been rolled forward to future
    $process->refresh();
    expect($process->next_due_at->isFuture())->toBeTrue();
});

test('running routine-alerts command twice does not duplicate anything on same day', function () {
    $process = Process::where('name', 'AMO Internal QA Audit')->first();
    $process->update([
        'next_due_at' => now()->startOfDay(),
        'frequency' => 'monthly',
    ]);

    // First run creates 1 audit and rolls date
    $this->artisan('audits:routine-alerts')->assertSuccessful();
    $auditCountAfterFirstRun = Audit::where('process_id', $process->id)->count();

    // Second run
    $this->artisan('audits:routine-alerts')->assertSuccessful();
    $auditCountAfterSecondRun = Audit::where('process_id', $process->id)->count();

    expect($auditCountAfterSecondRun)->toBe($auditCountAfterFirstRun);
});

test('field_auditor gets 403 on /audits list and show pages', function () {
    $fieldAuditor = User::where('email', 'auditor@ncaa.gov.ng')->first();
    $audit = Audit::first();

    $this->actingAs($fieldAuditor);

    $this->get('/audits')->assertStatus(403);
    $this->get("/audits/{$audit->id}")->assertStatus(403);
});
