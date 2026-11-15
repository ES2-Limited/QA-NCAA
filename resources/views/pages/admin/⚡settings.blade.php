<?php

use App\Contracts\AiService as AiServiceContract;
use App\Services\GeminiAiService;
use App\Services\SettingService;
use Database\Seeders\SettingSeeder;
use Flux\Flux;
use Illuminate\Support\Facades\Crypt;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('System Business Settings — NCAA QA/IA Admin')] class extends Component {
    use WithFileUploads;

    public string $activeTab = 'general';

    // Group 1: General & Branding
    public string $system_name = '';
    public string $organisation_name = '';
    public string $logo_url = '';
    public string $primary_color = '#2a1770';
    public string $accent_color = '#008751';
    public string $support_email = '';
    public string $timezone = 'Africa/Lagos';
    public string $date_format = 'M d, Y';
    public $new_logo = null;

    // Group 2: Audit Rules
    public array $finding_levels = [];
    public string $routine_alert_days = '14, 7';
    public string $allowed_frequencies = 'weekly, monthly, quarterly, bi-annual, annual';
    public string $default_answer_options = 'Compliant, Non-compliant, N/A';
    public string $non_compliant_answers = 'Non-compliant, non_compliant';
    public string $reference_format_ncr = 'NCR-{YYYY}-{####}';
    public string $reference_format_certificate = 'DSD-QA-{YYYY}-{####}';
    public string $reference_format_audit = 'AUD-{YYYY}-{CODE}-{###}';
    public string $upload_evidence_types = 'jpg,jpeg,png,webp,pdf,docx,doc';
    public int $upload_evidence_max_kb = 10240;
    public string $upload_document_types = 'pdf,docx';
    public int $upload_document_max_kb = 20480;

    // Group 3: Notifications
    public array $notificationEvents = [];

    // Group 4: AI Settings
    public bool $ai_enabled = true;
    public string $ai_model = 'gemini-2.5-flash';
    public string $ai_api_key = '';
    public string $ai_api_key_masked = '';
    public string $prompt_checklist = '';
    public string $prompt_summary = '';
    public string $prompt_report = '';
    public ?string $testAiResult = null;
    public bool $isTestingAi = false;

    // Group 5: Templates
    public string $cert_title = '';
    public string $cert_body = '';
    public string $cert_sig_left = '';
    public string $cert_sig_right = '';
    public string $report_header_title = '';
    public string $report_header_subtitle = '';
    public string $report_footer_text = '';

    public function mount(): void
    {
        if (! auth()->user()?->can('settings.manage')) {
            abort(403, 'Unauthorized.');
        }

        $this->loadSettings();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function loadSettings(): void
    {
        // 1. General & branding
        $this->system_name = (string) setting('general.system_name', 'Nigeria Civil Aviation Authority — DSD QA/IA System');
        $this->organisation_name = (string) setting('general.organisation_name', 'Nigeria Civil Aviation Authority');
        $this->logo_url = (string) setting('general.logo_url', '/images/ncaa-logo.png');
        $this->primary_color = (string) setting('general.primary_color', '#2a1770');
        $this->accent_color = (string) setting('general.accent_color', '#008751');
        $this->support_email = (string) setting('general.support_email', 'qa.support@ncaa.gov.ng');
        $this->timezone = (string) setting('general.timezone', 'Africa/Lagos');
        $this->date_format = (string) setting('general.date_format', 'M d, Y');

        // 2. Audit rules
        $defaultLevels = [
            ['level' => 1, 'name' => 'Level 1 — Critical / Safety Issue', 'cap_submission_days' => 3, 'closure_days' => 14],
            ['level' => 2, 'name' => 'Level 2 — Major Non-Conformance', 'cap_submission_days' => 14, 'closure_days' => 30],
            ['level' => 3, 'name' => 'Level 3 — Minor Observation', 'cap_submission_days' => 30, 'closure_days' => 90],
        ];
        $this->finding_levels = setting('audit.finding_levels', $defaultLevels);

        $alertDays = setting('audit.routine_alert_days', [14, 7]);
        $this->routine_alert_days = is_array($alertDays) ? implode(', ', $alertDays) : (string) $alertDays;

        $freqs = setting('audit.allowed_frequencies', ['weekly', 'monthly', 'quarterly', 'bi-annual', 'annual']);
        $this->allowed_frequencies = is_array($freqs) ? implode(', ', $freqs) : (string) $freqs;

        $ansOptions = setting('audit.default_answer_options', ['Compliant', 'Non-compliant', 'N/A']);
        $this->default_answer_options = is_array($ansOptions) ? implode(', ', $ansOptions) : (string) $ansOptions;

        $nonComp = setting('audit.non_compliant_answers', ['Non-compliant', 'non_compliant']);
        $this->non_compliant_answers = is_array($nonComp) ? implode(', ', $nonComp) : (string) $nonComp;

        $this->reference_format_ncr = (string) setting('audit.reference_format.ncr', 'NCR-{YYYY}-{####}');
        $this->reference_format_certificate = (string) setting('audit.reference_format.certificate', 'DSD-QA-{YYYY}-{####}');
        $this->reference_format_audit = (string) setting('audit.reference_format.audit', 'AUD-{YYYY}-{CODE}-{###}');

        $evRules = setting('audit.uploads.evidence', ['allowed_types' => 'jpg,jpeg,png,webp,pdf,docx,doc', 'max_size_kb' => 10240]);
        $this->upload_evidence_types = $evRules['allowed_types'] ?? 'jpg,jpeg,png,webp,pdf,docx,doc';
        $this->upload_evidence_max_kb = (int) ($evRules['max_size_kb'] ?? 10240);

        $docRules = setting('audit.uploads.documents', ['allowed_types' => 'pdf,docx', 'max_size_kb' => 20480]);
        $this->upload_document_types = $docRules['allowed_types'] ?? 'pdf,docx';
        $this->upload_document_max_kb = (int) ($docRules['max_size_kb'] ?? 20480);

        // 3. Notifications tab
        $events = [
            'audit_assigned' => 'Audit Assigned',
            'routine_due' => 'Routine Audit Due',
            'audit_submitted' => 'Audit Submitted',
            'ncr_raised' => 'NCR Raised',
            'cap_submitted' => 'CAP Submitted',
            'cap_reviewed' => 'CAP Reviewed',
            'approval_needed' => 'Approval Needed',
            'certificate_issued' => 'Certificate Issued',
        ];

        $this->notificationEvents = [];
        foreach ($events as $k => $label) {
            $default = SettingSeeder::defaults()["notifications.events.{$k}"]['value'] ?? [
                'email' => true,
                'in_app' => true,
                'subject' => "NCAA Notification: {$label}",
                'body' => "Update regarding {$label}.",
            ];
            $saved = setting("notifications.events.{$k}", $default);
            $this->notificationEvents[$k] = [
                'label' => $label,
                'email' => (bool) ($saved['email'] ?? true),
                'in_app' => (bool) ($saved['in_app'] ?? true),
                'subject' => (string) ($saved['subject'] ?? ''),
                'body' => (string) ($saved['body'] ?? ''),
            ];
        }

        // 4. AI tab
        $this->ai_enabled = (bool) setting('ai.enabled', true);
        $this->ai_model = (string) setting('ai.model', 'gemini-2.5-flash');

        $encryptedKey = (string) setting('ai.api_key', '');
        if (! empty($encryptedKey)) {
            try {
                $rawKey = Crypt::decryptString($encryptedKey);
                $this->ai_api_key_masked = substr($rawKey, 0, 4) . '••••••••••••••••' . substr($rawKey, -4);
            } catch (\Throwable) {
                $this->ai_api_key_masked = '••••••••••••••••';
            }
        } else {
            $this->ai_api_key_masked = config('services.gemini.key') ? '•••••••• (from .env)' : 'No API key configured';
        }
        $this->ai_api_key = '';

        $this->prompt_checklist = (string) setting('ai.prompts.checklist_generation', SettingSeeder::defaults()['ai.prompts.checklist_generation']['value']);
        $this->prompt_summary = (string) setting('ai.prompts.audit_summary', SettingSeeder::defaults()['ai.prompts.audit_summary']['value']);
        $this->prompt_report = (string) setting('ai.prompts.management_report', SettingSeeder::defaults()['ai.prompts.management_report']['value']);

        // 5. Templates tab
        $this->cert_title = (string) setting('templates.certificate.title', 'Internal Quality Audit Certificate');
        $this->cert_body = (string) setting('templates.certificate.body', SettingSeeder::defaults()['templates.certificate.body']['value']);
        $this->cert_sig_left = (string) setting('templates.certificate.signature_left_label', 'Head of Internal Audit');
        $this->cert_sig_right = (string) setting('templates.certificate.signature_right_label', 'Director of Special Duties, NCAA');

        $this->report_header_title = (string) setting('templates.report.header_title', 'Nigeria Civil Aviation Authority — DSD QA/IA System • Executive Report');
        $this->report_header_subtitle = (string) setting('templates.report.header_subtitle', 'Directorate of Special Duties | Quality Assurance & Internal Audit Division');
        $this->report_footer_text = (string) setting('templates.report.footer_text', 'Confidential • Nigeria Civil Aviation Authority • For internal executive oversight only.');
    }

    public function addFindingLevel(): void
    {
        $newLevelNum = count($this->finding_levels) + 1;
        $this->finding_levels[] = [
            'level' => $newLevelNum,
            'name' => "Level {$newLevelNum} Finding",
            'cap_submission_days' => 14,
            'closure_days' => 30,
        ];
    }

    public function removeFindingLevel(int $index): void
    {
        unset($this->finding_levels[$index]);
        $this->finding_levels = array_values($this->finding_levels);
    }

    public function saveGeneral(): void
    {
        $this->validate([
            'system_name' => ['required', 'string', 'max:255'],
            'organisation_name' => ['required', 'string', 'max:255'],
            'primary_color' => ['required', 'regex:/^#([a-f0-9]{6}|[a-f0-9]{3})$/i'],
            'accent_color' => ['required', 'regex:/^#([a-f0-9]{6}|[a-f0-9]{3})$/i'],
            'support_email' => ['required', 'email', 'max:255'],
            'timezone' => ['required', 'string', 'timezone'],
            'date_format' => ['required', 'string', 'max:30'],
            'new_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
        ]);

        $service = app(SettingService::class);

        if ($this->new_logo) {
            $path = $this->new_logo->store('branding', 'public');
            $this->logo_url = '/storage/' . $path;
            $service->set('general.logo_url', $this->logo_url, 'general', 'string');
            $this->new_logo = null;
        }

        $service->set('general.system_name', $this->system_name, 'general', 'string');
        $service->set('general.organisation_name', $this->organisation_name, 'general', 'string');
        $service->set('general.primary_color', $this->primary_color, 'general', 'string');
        $service->set('general.accent_color', $this->accent_color, 'general', 'string');
        $service->set('general.support_email', $this->support_email, 'general', 'string');
        $service->set('general.timezone', $this->timezone, 'general', 'string');
        $service->set('general.date_format', $this->date_format, 'general', 'string');

        Flux::toast(variant: 'success', text: __('General & branding settings saved successfully.'));
    }

    public function saveAuditRules(): void
    {
        $this->validate([
            'finding_levels' => ['required', 'array', 'min:1'],
            'finding_levels.*.level' => ['required', 'integer', 'min:1'],
            'finding_levels.*.name' => ['required', 'string', 'max:100'],
            'finding_levels.*.cap_submission_days' => ['required', 'integer', 'min:1', 'max:365'],
            'finding_levels.*.closure_days' => ['required', 'integer', 'min:1', 'max:365'],
            'routine_alert_days' => ['required', 'string'],
            'allowed_frequencies' => ['required', 'string'],
            'default_answer_options' => ['required', 'string'],
            'non_compliant_answers' => ['required', 'string'],
            'reference_format_ncr' => ['required', 'string', 'max:100'],
            'reference_format_certificate' => ['required', 'string', 'max:100'],
            'reference_format_audit' => ['required', 'string', 'max:100'],
            'upload_evidence_types' => ['required', 'string'],
            'upload_evidence_max_kb' => ['required', 'integer', 'min:512', 'max:102400'],
            'upload_document_types' => ['required', 'string'],
            'upload_document_max_kb' => ['required', 'integer', 'min:512', 'max:102400'],
        ]);

        $service = app(SettingService::class);

        $service->set('audit.finding_levels', $this->finding_levels, 'audit', 'json');

        $alertDaysArray = array_values(array_filter(array_map('intval', array_map('trim', explode(',', $this->routine_alert_days)))));
        $service->set('audit.routine_alert_days', $alertDaysArray, 'audit', 'json');

        $freqsArray = array_values(array_filter(array_map('trim', explode(',', $this->allowed_frequencies))));
        $service->set('audit.allowed_frequencies', $freqsArray, 'audit', 'json');

        $optsArray = array_values(array_filter(array_map('trim', explode(',', $this->default_answer_options))));
        $service->set('audit.default_answer_options', $optsArray, 'audit', 'json');

        $nonCompArray = array_values(array_filter(array_map('trim', explode(',', $this->non_compliant_answers))));
        $service->set('audit.non_compliant_answers', $nonCompArray, 'audit', 'json');

        $service->set('audit.reference_format.ncr', $this->reference_format_ncr, 'audit', 'string');
        $service->set('audit.reference_format.certificate', $this->reference_format_certificate, 'audit', 'string');
        $service->set('audit.reference_format.audit', $this->reference_format_audit, 'audit', 'string');

        $service->set('audit.uploads.evidence', [
            'allowed_types' => str_replace(' ', '', $this->upload_evidence_types),
            'max_size_kb' => $this->upload_evidence_max_kb,
        ], 'audit', 'json');

        $service->set('audit.uploads.documents', [
            'allowed_types' => str_replace(' ', '', $this->upload_document_types),
            'max_size_kb' => $this->upload_document_max_kb,
        ], 'audit', 'json');

        Flux::toast(variant: 'success', text: __('Audit rules and governance policies saved successfully.'));
    }

    public function saveNotifications(): void
    {
        $service = app(SettingService::class);

        foreach ($this->notificationEvents as $key => $ev) {
            $service->set("notifications.events.{$key}", [
                'email' => (bool) $ev['email'],
                'in_app' => (bool) $ev['in_app'],
                'subject' => trim($ev['subject']),
                'body' => trim($ev['body']),
            ], 'notifications', 'json');
        }

        Flux::toast(variant: 'success', text: __('Notification event routing and templates updated.'));
    }

    public function saveAi(): void
    {
        $this->validate([
            'ai_model' => ['required', 'string', 'max:100'],
            'prompt_checklist' => ['required', 'string'],
            'prompt_summary' => ['required', 'string'],
            'prompt_report' => ['required', 'string'],
        ]);

        $service = app(SettingService::class);

        $service->set('ai.enabled', $this->ai_enabled, 'ai', 'boolean');
        $service->set('ai.model', $this->ai_model, 'ai', 'string');

        if (! empty($this->ai_api_key)) {
            $encrypted = Crypt::encryptString(trim($this->ai_api_key));
            $service->set('ai.api_key', $encrypted, 'ai', 'string');
            $this->ai_api_key_masked = substr($this->ai_api_key, 0, 4) . '••••••••••••••••' . substr($this->ai_api_key, -4);
            $this->ai_api_key = '';
        }

        $service->set('ai.prompts.checklist_generation', $this->prompt_checklist, 'ai', 'string');
        $service->set('ai.prompts.audit_summary', $this->prompt_summary, 'ai', 'string');
        $service->set('ai.prompts.management_report', $this->prompt_report, 'ai', 'string');

        Flux::toast(variant: 'success', text: __('AI configuration and prompt templates saved.'));
    }

    public function testAi(): void
    {
        $this->isTestingAi = true;
        $this->testAiResult = null;

        try {
            $ai = app(AiServiceContract::class);
            $summary = $ai->summariseAudit("Sample Audit Context: Nigerian Civil Aviation Authority Routine Ramp Surveillance. 10 checklist items checked, 1 Level 2 NCR on calibration certification overdue.");
            $this->testAiResult = $summary;
            Flux::toast(variant: 'success', text: __('AI test completed successfully!'));
        } catch (\Throwable $e) {
            $this->testAiResult = 'Error: ' . $e->getMessage();
            Flux::toast(variant: 'danger', text: __('AI test failed. Check API key and configuration.'));
        } finally {
            $this->isTestingAi = false;
        }
    }

    public function saveTemplates(): void
    {
        $this->validate([
            'cert_title' => ['required', 'string', 'max:255'],
            'cert_body' => ['required', 'string'],
            'cert_sig_left' => ['required', 'string', 'max:100'],
            'cert_sig_right' => ['required', 'string', 'max:100'],
            'report_header_title' => ['required', 'string', 'max:255'],
            'report_header_subtitle' => ['required', 'string', 'max:255'],
            'report_footer_text' => ['required', 'string', 'max:255'],
        ]);

        $service = app(SettingService::class);

        $service->set('templates.certificate.title', $this->cert_title, 'templates', 'string');
        $service->set('templates.certificate.body', $this->cert_body, 'templates', 'string');
        $service->set('templates.certificate.signature_left_label', $this->cert_sig_left, 'templates', 'string');
        $service->set('templates.certificate.signature_right_label', $this->cert_sig_right, 'templates', 'string');

        $service->set('templates.report.header_title', $this->report_header_title, 'templates', 'string');
        $service->set('templates.report.header_subtitle', $this->report_header_subtitle, 'templates', 'string');
        $service->set('templates.report.footer_text', $this->report_footer_text, 'templates', 'string');

        Flux::toast(variant: 'success', text: __('Certificate and report templates saved successfully.'));
    }

    public function resetToDefault(?string $group = null): void
    {
        $group = $group ?? $this->activeTab;
        $defaults = SettingSeeder::defaults();
        $service = app(SettingService::class);

        foreach ($defaults as $key => $data) {
            if ($data['group'] === $group || $group === 'all') {
                $service->set($key, $data['value'], $data['group'], $data['type']);
            }
        }

        $this->new_logo = null;
        $this->loadSettings();

        Flux::toast(variant: 'success', text: __('Settings for :group reset to default.', ['group' => ucfirst($group)]));
    }
}; ?>

<div class="space-y-6 w-full max-w-5xl mx-auto pb-12">
    <!-- Breadcrumb & Header -->
    <div>
        <nav class="flex items-center text-xs text-zinc-500 mb-1 space-x-1.5" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200 transition">Dashboard</a>
            <span>/</span>
            <span class="text-zinc-800 dark:text-zinc-200 font-medium">Administration</span>
            <span>/</span>
            <span class="text-emerald-700 dark:text-emerald-400 font-semibold">System Settings</span>
        </nav>
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('System Settings & Configuration') }}</flux:heading>
                <flux:subheading size="lg">{{ __('Configure dynamic business parameters, branding identity, notifications, AI prompts, and statutory templates') }}</flux:subheading>
            </div>
            <flux:button wire:click="resetToDefault('{{ $activeTab }}')" variant="outline" icon="arrow-path" class="text-xs">
                {{ __('Reset Group to Defaults') }}
            </flux:button>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-zinc-200 dark:border-zinc-800">
        <nav class="flex space-x-6 -mb-px overflow-x-auto" aria-label="Settings Group Tabs">
            <button
                type="button"
                wire:click="setTab('general')"
                class="py-3 px-1 text-sm font-semibold border-b-2 transition flex items-center gap-2 whitespace-nowrap {{ $activeTab === 'general' ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
            >
                <flux:icon name="paint-brush" class="size-4" />
                <span>General &amp; Branding</span>
            </button>

            <button
                type="button"
                wire:click="setTab('audit')"
                class="py-3 px-1 text-sm font-semibold border-b-2 transition flex items-center gap-2 whitespace-nowrap {{ $activeTab === 'audit' ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
            >
                <flux:icon name="clipboard-document-check" class="size-4" />
                <span>Audit Rules</span>
            </button>

            <button
                type="button"
                wire:click="setTab('notifications')"
                class="py-3 px-1 text-sm font-semibold border-b-2 transition flex items-center gap-2 whitespace-nowrap {{ $activeTab === 'notifications' ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
            >
                <flux:icon name="bell" class="size-4" />
                <span>Notifications</span>
            </button>

            <button
                type="button"
                wire:click="setTab('ai')"
                class="py-3 px-1 text-sm font-semibold border-b-2 transition flex items-center gap-2 whitespace-nowrap {{ $activeTab === 'ai' ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
            >
                <flux:icon name="sparkles" class="size-4" />
                <span>AI Intelligence</span>
            </button>

            <button
                type="button"
                wire:click="setTab('templates')"
                class="py-3 px-1 text-sm font-semibold border-b-2 transition flex items-center gap-2 whitespace-nowrap {{ $activeTab === 'templates' ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
            >
                <flux:icon name="document-text" class="size-4" />
                <span>Templates</span>
            </button>
        </nav>
    </div>

    <!-- TAB 1: General & Branding -->
    @if($activeTab === 'general')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-xs space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Organisation Identity & Visual Theme Tokens') }}</flux:heading>
                <flux:subheading>{{ __('Colors dynamically hydrate the CSS theme tokens throughout the web app, sidebar, and PDF reports') }}</flux:subheading>
            </div>

            <form wire:submit="saveGeneral" class="space-y-6 max-w-2xl">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input wire:model="system_name" :label="__('System Name')" description="Appears on page titles, headers, and certificates" required />
                    <flux:input wire:model="organisation_name" :label="__('Organisation Name')" description="Statutory authority name" required />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <flux:label>{{ __('Primary NCAA Brand Colour') }}</flux:label>
                        <div class="flex items-center space-x-3 mt-1.5">
                            <input type="color" wire:model.live="primary_color" class="h-9 w-12 rounded border border-zinc-300 dark:border-zinc-700 cursor-pointer bg-transparent" />
                            <flux:input wire:model="primary_color" class="font-mono text-xs" required />
                        </div>
                    </div>

                    <div>
                        <flux:label>{{ __('Accent Brand Colour') }}</flux:label>
                        <div class="flex items-center space-x-3 mt-1.5">
                            <input type="color" wire:model.live="accent_color" class="h-9 w-12 rounded border border-zinc-300 dark:border-zinc-700 cursor-pointer bg-transparent" />
                            <flux:input wire:model="accent_color" class="font-mono text-xs" required />
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl space-y-3 border border-zinc-200 dark:border-zinc-750">
                    <flux:label>{{ __('Official NCAA Logo') }}</flux:label>
                    <div class="flex items-center space-x-4">
                        @if($logo_url)
                            <div class="p-2 bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                <img src="{{ $logo_url }}" alt="Logo" class="h-10 w-auto object-contain" />
                            </div>
                        @endif
                        <div class="flex-1">
                            <flux:input type="file" wire:model="new_logo" description="PNG, SVG, or WEBP up to 2MB" />
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <flux:input wire:model="support_email" :label="__('System Support Email')" type="email" required />
                    <flux:input wire:model="timezone" :label="__('System Timezone')" description="e.g. Africa/Lagos" required />
                    <flux:input wire:model="date_format" :label="__('Date Display Format')" description="e.g. M d, Y" required />
                </div>

                <div class="flex justify-end pt-2">
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('Save General Settings') }}
                    </flux:button>
                </div>
            </form>
        </div>
    @endif

    <!-- TAB 2: Audit Rules -->
    @if($activeTab === 'audit')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-xs space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Audit Governance Rules & Parameters') }}</flux:heading>
                <flux:subheading>{{ __('Finding classification levels, CAP submission deadlines, reference formats, and upload restrictions') }}</flux:subheading>
            </div>

            <form wire:submit="saveAuditRules" class="space-y-6">
                <!-- Finding Levels -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:label class="text-sm font-semibold">{{ __('Finding Severity Levels & Deadlines') }}</flux:label>
                        <flux:button type="button" wire:click="addFindingLevel" size="xs" variant="outline" icon="plus">
                            {{ __('Add Level') }}
                        </flux:button>
                    </div>

                    <div class="space-y-2">
                        @foreach($finding_levels as $index => $fl)
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-center p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700">
                                <div class="sm:col-span-1 text-center font-mono text-xs font-bold text-zinc-500">
                                    L{{ $fl['level'] }}
                                </div>
                                <div class="sm:col-span-5">
                                    <flux:input wire:model="finding_levels.{{ $index }}.name" placeholder="Level Title" size="sm" required />
                                </div>
                                <div class="sm:col-span-3">
                                    <flux:input type="number" wire:model="finding_levels.{{ $index }}.cap_submission_days" placeholder="CAP Days" size="sm" min="1" max="365" required />
                                </div>
                                <div class="sm:col-span-2">
                                    <flux:input type="number" wire:model="finding_levels.{{ $index }}.closure_days" placeholder="Closure Days" size="sm" min="1" max="365" required />
                                </div>
                                <div class="sm:col-span-1 text-right">
                                    <flux:button type="button" wire:click="removeFindingLevel({{ $index }})" size="xs" variant="ghost" icon="trash" :disabled="count($finding_levels) <= 1" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Reference Formats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:input wire:model="reference_format_ncr" :label="__('NCR Reference Format')" description="Tokens: {YYYY}, {####}" required />
                    <flux:input wire:model="reference_format_certificate" :label="__('Certificate Format')" description="Tokens: {YYYY}, {####}" required />
                    <flux:input wire:model="reference_format_audit" :label="__('Audit Reference Format')" description="Tokens: {YYYY}, {CODE}, {###}" required />
                </div>

                <!-- Upload Constraints -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                    <div class="space-y-3 p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <flux:label class="font-bold text-xs uppercase tracking-wider text-zinc-500">{{ __('Evidence Attachments') }}</flux:label>
                        <flux:input wire:model="upload_evidence_types" label="Allowed Types (comma-separated)" required />
                        <flux:input type="number" wire:model="upload_evidence_max_kb" label="Max File Size (KB)" min="512" max="102400" required />
                    </div>

                    <div class="space-y-3 p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <flux:label class="font-bold text-xs uppercase tracking-wider text-zinc-500">{{ __('Document Library Files') }}</flux:label>
                        <flux:input wire:model="upload_document_types" label="Allowed Types (comma-separated)" required />
                        <flux:input type="number" wire:model="upload_document_max_kb" label="Max File Size (KB)" min="512" max="102400" required />
                    </div>
                </div>

                <div class="flex justify-end pt-4">
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('Save Audit Rules') }}
                    </flux:button>
                </div>
            </form>
        </div>
    @endif

    <!-- TAB 3: Notifications -->
    @if($activeTab === 'notifications')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-xs space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Event Notification Channels & Message Templates') }}</flux:heading>
                <flux:subheading>{{ __('Toggle delivery channels (email & in-app) per event and customize subject/body templates with dynamic placeholders') }}</flux:subheading>
            </div>

            <form wire:submit="saveNotifications" class="space-y-6">
                <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach($notificationEvents as $evKey => $evData)
                        <div class="py-5 first:pt-0 last:pb-0 space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div class="flex items-center space-x-2">
                                    <flux:badge size="sm" color="zinc" class="font-mono text-xs">{{ $evKey }}</flux:badge>
                                    <span class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">{{ $evData['label'] }}</span>
                                </div>
                                <div class="flex items-center space-x-4 text-xs">
                                    <label class="flex items-center space-x-1.5 cursor-pointer">
                                        <input type="checkbox" wire:model="notificationEvents.{{ $evKey }}.in_app" class="rounded text-emerald-600 focus:ring-emerald-500 border-zinc-300 dark:border-zinc-700" />
                                        <span>In-App Bell</span>
                                    </label>
                                    <label class="flex items-center space-x-1.5 cursor-pointer">
                                        <input type="checkbox" wire:model="notificationEvents.{{ $evKey }}.email" class="rounded text-emerald-600 focus:ring-emerald-500 border-zinc-300 dark:border-zinc-700" />
                                        <span>Email Notification</span>
                                    </label>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <flux:input
                                    wire:model="notificationEvents.{{ $evKey }}.subject"
                                    label="Subject Line Template"
                                    size="sm"
                                    required
                                />

                                <flux:textarea
                                    wire:model="notificationEvents.{{ $evKey }}.body"
                                    label="Message Body Template"
                                    rows="2"
                                    required
                                />
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('Save Notification Settings') }}
                    </flux:button>
                </div>
            </form>
        </div>
    @endif

    <!-- TAB 4: AI Intelligence -->
    @if($activeTab === 'ai')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-xs space-y-6">
            <div>
                <flux:heading size="lg">{{ __('AI Assistance Configuration & Prompt Engineering') }}</flux:heading>
                <flux:subheading>{{ __('Manage Google Gemini API credentials, models, and custom system prompt templates') }}</flux:subheading>
            </div>

            <form wire:submit="saveAi" class="space-y-6">
                <!-- Toggle & Credentials -->
                <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl space-y-4 border border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100 block">Enable AI Assistance</span>
                            <span class="text-xs text-zinc-500">When disabled, the app cleanly falls back to deterministic local heuristic generators</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="ai_enabled" class="sr-only peer" />
                            <div class="w-11 h-6 bg-zinc-200 peer-focus:outline-none rounded-full peer dark:bg-zinc-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-zinc-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                        <flux:input wire:model="ai_model" label="Gemini Model Identifier" placeholder="gemini-2.5-flash" required />

                        <div>
                            <flux:input
                                wire:model="ai_api_key"
                                type="password"
                                label="Gemini API Key (Encrypted at rest)"
                                placeholder="Enter new key to replace..."
                            />
                            <div class="text-[11px] text-zinc-500 mt-1">
                                Current key status: <span class="font-mono text-emerald-600">{{ $ai_api_key_masked }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Prompt Templates -->
                <div class="space-y-4">
                    <flux:heading size="sm">{{ __('Editable Prompt Templates') }}</flux:heading>

                    <div class="space-y-2">
                        <flux:textarea
                            wire:model="prompt_checklist"
                            label="1. Checklist Questionnaire Generation"
                            description="Available placeholders: {count}, {scope}, {requirement_text}"
                            rows="4"
                            required
                        />
                    </div>

                    <div class="space-y-2">
                        <flux:textarea
                            wire:model="prompt_summary"
                            label="2. Leadership Audit Summary"
                            description="Available placeholders: {audit_context}"
                            rows="3"
                            required
                        />
                    </div>

                    <div class="space-y-2">
                        <flux:textarea
                            wire:model="prompt_report"
                            label="3. Executive Management Report"
                            description="Available placeholders: {context}"
                            rows="3"
                            required
                        />
                    </div>
                </div>

                <!-- Action & Test buttons -->
                <div class="flex items-center justify-between pt-4 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button type="button" wire:click="testAi" variant="outline" icon="bolt" :disabled="$isTestingAi">
                        {{ $isTestingAi ? __('Testing Connection...') : __('Test AI Connection') }}
                    </flux:button>

                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('Save AI Settings') }}
                    </flux:button>
                </div>

                @if($testAiResult)
                    <div class="p-4 bg-zinc-50 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-xl space-y-1">
                        <div class="text-xs font-bold uppercase text-zinc-500">Test Output Preview</div>
                        <div class="text-xs text-zinc-700 dark:text-zinc-300 font-mono whitespace-pre-wrap">{{ $testAiResult }}</div>
                    </div>
                @endif
            </form>
        </div>
    @endif

    <!-- TAB 5: Templates -->
    @if($activeTab === 'templates')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-xs space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Statutory Certificate & Executive Report Templates') }}</flux:heading>
                <flux:subheading>{{ __('Configure legal wording, certification statements, signature titles, and executive report headers/footers') }}</flux:subheading>
            </div>

            <form wire:submit="saveTemplates" class="space-y-6">
                <!-- Certificate Wording -->
                <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl space-y-4 border border-zinc-200 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Quality Audit Certificate (A4 PDF)') }}</flux:heading>

                    <flux:input wire:model="cert_title" label="Certificate Main Title" required />

                    <flux:textarea wire:model="cert_body" label="Certification Statement / Body Paragraph" rows="3" required />

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:input wire:model="cert_sig_left" label="Left Signature Label" placeholder="Head of Internal Audit" required />
                        <flux:input wire:model="cert_sig_right" label="Right Signature Label" placeholder="Director of Special Duties, NCAA" required />
                    </div>
                </div>

                <!-- Executive Report Header/Footer -->
                <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl space-y-4 border border-zinc-200 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Executive Management Report (PDF)') }}</flux:heading>

                    <flux:input wire:model="report_header_title" label="Header Title" required />
                    <flux:input wire:model="report_header_subtitle" label="Header Subtitle / Division Meta" required />
                    <flux:input wire:model="report_footer_text" label="Confidentiality Footer Text" required />
                </div>

                <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('Save PDF Templates') }}
                    </flux:button>
                </div>
            </form>
        </div>
    @endif

</div>
