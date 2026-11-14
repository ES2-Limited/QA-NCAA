<?php

use App\Models\Audit;
use App\Models\User;
use App\Services\AiServiceInterface;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->artisan('db:seed');
});

test('exactly seven specified roles are seeded', function () {
    $expectedRoles = [
        'system_admin',
        'lead_auditor',
        'field_auditor',
        'reviewer',
        'head_of_audit',
        'director',
        'auditee',
    ];

    $dbRoles = Role::pluck('name')->all();
    sort($expectedRoles);
    sort($dbRoles);

    expect($dbRoles)->toEqual($expectedRoles);
});

test('one demo user is seeded for each role', function () {
    $roleUsers = [
        'system_admin' => 'admin@ncaa.gov.ng',
        'lead_auditor' => 'lead_auditor@ncaa.gov.ng',
        'field_auditor' => 'auditor@ncaa.gov.ng',
        'reviewer' => 'reviewer@ncaa.gov.ng',
        'head_of_audit' => 'head_of_audit@ncaa.gov.ng',
        'director' => 'director@ncaa.gov.ng',
        'auditee' => 'auditee@ncaa.gov.ng',
    ];

    foreach ($roleUsers as $role => $email) {
        $user = User::where('email', $email)->first();
        expect($user)->not->toBeNull();
        expect($user->hasRole($role))->toBeTrue();
    }
});

test('guest is redirected to login from auditor routes', function () {
    $response = $this->get('/auditor');
    $response->assertRedirect('/login');
});

test('authenticated field auditor can access dashboard and execution checklist', function () {
    $auditor = User::where('email', 'auditor@ncaa.gov.ng')->first();
    $audit = Audit::first();

    $response = $this->actingAs($auditor)->get('/auditor');
    $response->assertStatus(200);
    $response->assertSee('QA / Internal Audit Field Operations');

    $execResponse = $this->actingAs($auditor)->get(route('auditor.execute', $audit->id));
    $execResponse->assertStatus(200);
    $execResponse->assertSee($audit->reference_number);
});

test('system_admin has exclusive access to admin management routes', function () {
    $admin = User::where('email', 'admin@ncaa.gov.ng')->first();
    $auditee = User::where('email', 'auditee@ncaa.gov.ng')->first();

    // system_admin allowed
    $response = $this->actingAs($admin)->get('/admin/directorates');
    $response->assertStatus(200);
    $response->assertSee('NCAA Directorates');

    // non system_admin receives 403 Forbidden
    $forbidden = $this->actingAs($auditee)->get('/admin/directorates');
    $forbidden->assertStatus(403);
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
