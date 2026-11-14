<?php

use App\Models\Finding;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

// ── Dashboard access per role ─────────────────────────────────────────────────

test('management roles can access the dashboard', function (string $email) {
    $user = User::where('email', $email)->firstOrFail();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
})->with([
    'system admin' => ['admin@ncaa.gov.ng'],
    'lead auditor' => ['lead_auditor@ncaa.gov.ng'],
    'head of audit' => ['head_of_audit@ncaa.gov.ng'],
    'director' => ['director@ncaa.gov.ng'],
]);

test('non-management roles are redirected from dashboard', function (string $email, string $expectedRoute) {
    $user = User::where('email', $email)->firstOrFail();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route($expectedRoute));
})->with([
    'field auditor' => ['auditor@ncaa.gov.ng', 'field.index'],
    'reviewer' => ['reviewer@ncaa.gov.ng', 'review.index'],
    'auditee' => ['auditee@ncaa.gov.ng', 'ncrs.index'],
]);

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

// ── KPI counts match seed data ────────────────────────────────────────────────

test('dashboard KPI counts match seeded data', function () {
    $admin = User::where('email', 'admin@ncaa.gov.ng')->firstOrFail();

    $component = Livewire::actingAs($admin)->test('pages::dashboard');

    $component->assertSet('reportMarkdown', ''); // starts empty

    // Certificates seeded = 3
    $component->assertSee('3');

    // Open NCRs seeded >= 5
    $openNcrs = Finding::where('status', 'open')->count();
    expect($openNcrs)->toBeGreaterThanOrEqual(5);

    // Overdue CAPs seeded >= 2
    $overdueCaps = Finding::where('status', 'open')
        ->where(fn ($q) => $q->where('cap_due_at', '<', now())->orWhere('due_date', '<', now()))
        ->count();
    expect($overdueCaps)->toBeGreaterThanOrEqual(2);
});

// ── Non-management gets 403 on the Livewire component ────────────────────────

test('non-management role gets 403 from the Livewire component directly', function () {
    $auditor = User::where('email', 'auditor@ncaa.gov.ng')->firstOrFail();

    Livewire::actingAs($auditor)
        ->test('pages::dashboard')
        ->assertForbidden();
});

// ── AI report falls back to FakeAiService ────────────────────────────────────

test('AI report generation falls back to FakeAiService when no Gemini key is configured', function () {
    config(['services.gemini.key' => '']);

    $admin = User::where('email', 'admin@ncaa.gov.ng')->firstOrFail();

    Livewire::actingAs($admin)
        ->test('pages::dashboard')
        ->set('reportPeriod', 'last_30_days')
        ->call('generateReport')
        ->assertHasNoErrors()
        ->assertSet('reportMarkdown', fn ($value) => str_contains($value, 'Executive Overview') || str_contains($value, 'NCAA') || strlen($value) > 50);
});
