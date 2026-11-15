<?php

use App\Contracts\AiService;
use App\Models\Directorate;
use App\Models\Process;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->artisan('db:seed');
});

test('lead_auditor can create a routine process with questions', function () {
    $leadAuditor = User::where('email', 'lead_auditor@ncaa.gov.ng')->first();
    expect($leadAuditor)->not->toBeNull();

    $directorate = Directorate::where('code', 'DAWS')->first();
    expect($directorate)->not->toBeNull();

    $this->actingAs($leadAuditor);

    $test = Livewire::test('pages::processes.form')
        ->set('name', 'Airworthiness Engineering Routine Audit')
        ->set('description', 'Comprehensive inspection of maintenance protocols.')
        ->set('directorate_id', $directorate->id)
        ->set('type', 'routine')
        ->set('frequency', 'monthly')
        ->set('first_due_date', now()->addDays(14)->toDateString())
        ->set('questions', [
            [
                'question' => 'Are tool calibration tags current?',
                'type' => 'radio',
                'options' => ['Compliant', 'Non-compliant', 'N/A'],
                'requirement_ref' => 'Nig.CARs Part 6',
                'guidance' => 'Sample 10 tools',
                'source' => 'manual',
            ],
            [
                'question' => 'Provide details of any open discrepancies.',
                'type' => 'text',
                'options' => null,
                'requirement_ref' => null,
                'guidance' => null,
                'source' => 'manual',
            ],
        ])
        ->call('save', 'active')
        ->assertHasNoErrors();

    $proc = Process::where('name', 'Airworthiness Engineering Routine Audit')->first();
    expect($proc)->not->toBeNull();
    $test->assertRedirect(route('processes.show', $proc->id));

    $this->assertDatabaseHas('processes', [
        'name' => 'Airworthiness Engineering Routine Audit',
        'type' => 'routine',
        'frequency' => 'monthly',
        'status' => 'active',
        'created_by' => $leadAuditor->id,
    ]);

    expect($proc->questions)->toHaveCount(2);
    expect($proc->next_due_at->toDateString())->toBe(now()->addDays(14)->toDateString());
});

test('activating process without questions fails validation', function () {
    $leadAuditor = User::where('email', 'lead_auditor@ncaa.gov.ng')->first();
    $directorate = Directorate::first();

    $this->actingAs($leadAuditor);

    Livewire::test('pages::processes.form')
        ->set('name', 'Empty Process Template')
        ->set('directorate_id', $directorate->id)
        ->set('type', 'unplanned')
        ->set('questions', [])
        ->call('save', 'active')
        ->assertHasErrors(['questions']);

    $this->assertDatabaseMissing('processes', [
        'name' => 'Empty Process Template',
    ]);
});

test('field_auditor is redirected to field on /processes', function () {
    $fieldAuditor = User::where('email', 'auditor@ncaa.gov.ng')->first();
    expect($fieldAuditor)->not->toBeNull();

    $this->actingAs($fieldAuditor);

    $response = $this->get('/processes');
    $response->assertRedirect(route('field.index'));
});

test('AI generate returns draft questions via FakeAiService', function () {
    $leadAuditor = User::where('email', 'lead_auditor@ncaa.gov.ng')->first();
    $this->actingAs($leadAuditor);

    $aiService = app(AiService::class);
    $mockQuestions = $aiService->generateChecklist('', 'Aviation Fuel Testing Facility', 5);

    expect($mockQuestions)->toBeArray();
    expect(count($mockQuestions))->toBe(5);
    expect($mockQuestions[0])->toHaveKeys(['question', 'type', 'options', 'requirement_ref', 'guidance']);

    Livewire::test('pages::processes.form')
        ->set('aiScope', 'Aviation Fuel Testing Facility')
        ->set('aiCount', 5)
        ->call('generateWithAi')
        ->assertHasNoErrors()
        ->assertSet('showAiModal', false)
        ->assertCount('questions', 5);
});
