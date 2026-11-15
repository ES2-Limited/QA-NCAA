<?php

use App\Models\Audit;
use App\Models\Directorate;
use App\Models\User;
use App\Notifications\AuditAssigned;
use App\Services\FakeAiService;
use App\Services\GeminiAiService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
    (new SettingSeeder)->run();
});

test('admin can view and switch between all 5 settings tabs', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');

    Livewire::actingAs($admin)
        ->test('pages::admin.settings')
        ->assertOk()
        ->assertSee('General & Branding')
        ->assertSee('Audit Rules')
        ->assertSee('Notifications')
        ->assertSee('AI Intelligence')
        ->assertSee('Templates')
        ->call('setTab', 'notifications')
        ->assertSee('Event Notification Channels')
        ->call('setTab', 'ai')
        ->assertSee('AI Assistance Configuration')
        ->call('setTab', 'templates')
        ->assertSee('Statutory Certificate & Executive Report Templates');
});

test('a disabled event in notifications sends nothing via mail or in-app', function () {
    $dir = Directorate::firstOrCreate(['code' => 'TEST_DIR'], ['name' => 'Testing Directorate']);
    $user = User::factory()->create();

    $audit = Audit::create([
        'title' => 'Ramp Conformance Inspection',
        'reference_number' => 'AUD-2026-TEST-001',
        'directorate_id' => $dir->id,
        'lead_auditor_id' => $user->id,
        'status' => 'scheduled',
    ]);

    // 1. By default, audit_assigned has both email and in_app enabled
    $notification = new AuditAssigned($audit);
    expect($notification->via($user))->toContain('database');
    expect($notification->via($user))->toContain('mail');

    // 2. Disable email and in_app in settings for audit_assigned
    setting([
        'notifications.events.audit_assigned' => [
            'email' => false,
            'in_app' => false,
            'subject' => 'Disabled subject',
            'body' => 'Disabled body',
        ],
    ]);

    $notification = new AuditAssigned($audit);
    // via() should return empty array -> sending nothing
    expect($notification->via($user))->toBeEmpty();
});

test('edited notification subject and body placeholders are interpolated', function () {
    $dir = Directorate::firstOrCreate(['code' => 'TEST_DIR'], ['name' => 'Testing Directorate']);
    $user = User::factory()->create(['name' => 'Capt. Bello']);

    $audit = Audit::create([
        'title' => 'Avionics Maintenance Quality Check',
        'reference_number' => 'AUD-AV-999',
        'directorate_id' => $dir->id,
        'lead_auditor_id' => $user->id,
        'status' => 'scheduled',
        'scheduled_for' => now()->addDays(5),
    ]);

    // Customise template with placeholders
    setting([
        'notifications.events.audit_assigned' => [
            'email' => true,
            'in_app' => true,
            'subject' => 'CUSTOM ASSIGNMENT: {audit_title}',
            'body' => 'Notice for engagement {audit_ref} due on {due_date}.',
        ],
    ]);

    $notif = new AuditAssigned($audit);

    $mail = $notif->toMail($user);
    expect($mail->subject)->toBe('CUSTOM ASSIGNMENT: Avionics Maintenance Quality Check');
    expect($mail->introLines[0])->toContain('Notice for engagement AUD-AV-999');

    $dbArray = $notif->toArray($user);
    expect($dbArray['title'])->toBe('CUSTOM ASSIGNMENT: Avionics Maintenance Quality Check');
    expect($dbArray['message'])->toContain('Notice for engagement AUD-AV-999');
});

test('an edited AI prompt template is used during AI generation', function () {
    // Customise the audit summary prompt
    setting([
        'ai.enabled' => true,
        'ai.api_key' => Crypt::encryptString('TEST_FAKE_KEY'),
        'ai.model' => 'gemini-2.5-flash',
        'ai.prompts.audit_summary' => 'CUSTOM PROMPT PREFIX: Please inspect: {audit_context}',
    ]);

    $recordedPrompt = null;
    Http::fake([
        'generativelanguage.googleapis.com/*' => function (Request $request) use (&$recordedPrompt) {
            $recordedPrompt = $request['contents'][0]['parts'][0]['text'] ?? '';

            return Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Custom AI Summary Generated'],
                            ],
                        ],
                    ],
                ],
            ], 200);
        },
    ]);

    $aiService = new GeminiAiService(new FakeAiService);
    $result = $aiService->summariseAudit('Sample Audit Input');

    expect($result)->toBe('Custom AI Summary Generated');
    expect($recordedPrompt)->toContain('CUSTOM PROMPT PREFIX: Please inspect: Sample Audit Input');
});

test('when AI is disabled, the service cleanly falls back to fake/local heuristic generation', function () {
    setting([
        'ai.enabled' => false,
        'ai.api_key' => '',
    ]);

    $aiService = new GeminiAiService(new FakeAiService);

    // Call summariseAudit
    $summary = $aiService->summariseAudit('Sample Audit Context');
    expect($summary)->toBeString();
    expect($summary)->not->toBeEmpty();

    // Call generateChecklist
    $questions = $aiService->generateChecklist('Standard Runway Maintenance Specs', 'Runway Safety', 3);
    expect($questions)->toBeArray();
    expect(count($questions))->toBe(3);
    expect($questions[0])->toHaveKey('question');
});

test('custom certificate and report templates are saved and applied', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');

    Livewire::actingAs($admin)
        ->test('pages::admin.settings')
        ->call('setTab', 'templates')
        ->set('cert_title', 'Statutory Oversight Compliance Certificate')
        ->set('cert_sig_left', 'Chief Quality Auditor')
        ->set('report_footer_text', 'NCAA Internal Confidentiality Clause 4.2')
        ->call('saveTemplates')
        ->assertHasNoErrors();

    expect(setting('templates.certificate.title'))->toBe('Statutory Oversight Compliance Certificate');
    expect(setting('templates.certificate.signature_left_label'))->toBe('Chief Quality Auditor');
    expect(setting('templates.report.footer_text'))->toBe('NCAA Internal Confidentiality Clause 4.2');
});
