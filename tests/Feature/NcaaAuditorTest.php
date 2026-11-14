<?php

use App\Models\Audit;
use App\Services\AiServiceInterface;

beforeEach(function () {
    $this->artisan('db:seed');
});

test('field auditor dashboard renders successfully', function () {
    $response = $this->get('/auditor');

    $response->assertStatus(200);
    $response->assertSee('Directorate of Special Duties (DSD)');
    $response->assertSee('QA / Internal Audit Field Operations');
});

test('pwa manifest and service worker files exist in public directory', function () {
    expect(file_exists(public_path('manifest.json')))->toBeTrue();
    expect(file_exists(public_path('sw.js')))->toBeTrue();
    expect(file_exists(public_path('icons/icon-192.png')))->toBeTrue();
});

test('field checklist execution page renders and displays items', function () {
    $audit = Audit::first();
    expect($audit)->not->toBeNull();

    $response = $this->get(route('auditor.execute', $audit->id));

    $response->assertStatus(200);
    $response->assertSee($audit->reference_number);
    $response->assertSee('Field Checklist');
});

test('finding report page allows logging non-conformance with AI risk guidance', function () {
    $audit = Audit::first();
    expect($audit)->not->toBeNull();

    $response = $this->get(route('auditor.report-finding', $audit->id));

    $response->assertStatus(200);
    $response->assertSee('Log Non-Conformance / Deficiency');
    $response->assertSee('Run AI Risk Analysis');
});

test('ai service generates risk analysis and executive summary offline fallback', function () {
    $aiService = app(AiServiceInterface::class);

    $analysis = $aiService->analyzeFinding(
        'Critical Fuel Hydrant Leak',
        'Fuel leaking near jet engines with no emergency shutoff activated.',
        'Nig.CARs Part 8'
    );

    expect($analysis)->toHaveKey('severity_recommendation');
    expect($analysis['severity_recommendation'])->toBe('critical');

    $summary = $aiService->generateExecutiveSummary(
        'Surveillance Audit',
        'DAWS',
        85,
        ['critical' => 1, 'major' => 2, 'minor' => 0]
    );

    expect($summary)->toContain('Executive Audit Summary');
    expect($summary)->toContain('Directorate of Special Duties');
});
