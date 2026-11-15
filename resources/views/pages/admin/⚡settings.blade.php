<?php

use App\Services\SettingService;
use Database\Seeders\SettingSeeder;
use Flux\Flux;
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

    // Temporary upload for logo
    public $new_logo = null;

    // Group 2: Audit Rules
    // Finding Levels: array of ['level' => int, 'name' => string, 'cap_submission_days' => int, 'closure_days' => int]
    public array $finding_levels = [];

    // Routine Alert Days & Allowed Frequencies
    public string $routine_alert_days = '14, 7';
    public string $allowed_frequencies = 'weekly, monthly, quarterly, bi-annual, annual';

    // Default question answers & Non-compliant trigger values
    public string $default_answer_options = 'Compliant, Non-compliant, N/A';
    public string $non_compliant_answers = 'Non-compliant, non_compliant';

    // Reference Formats
    public string $reference_format_ncr = 'NCR-{YYYY}-{####}';
    public string $reference_format_certificate = 'DSD-QA-{YYYY}-{####}';
    public string $reference_format_audit = 'AUD-{YYYY}-{CODE}-{###}';

    // Upload Rules
    public string $upload_evidence_types = 'jpg,jpeg,png,webp,pdf,docx,doc';
    public int $upload_evidence_max_kb = 10240;
    public string $upload_document_types = 'pdf,docx';
    public int $upload_document_max_kb = 20480;

    public function mount(): void
    {
        if (! auth()->user()?->hasRole('system_admin')) {
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
        // General & branding
        $this->system_name = (string) setting('general.system_name', 'Nigeria Civil Aviation Authority — DSD QA/IA System');
        $this->organisation_name = (string) setting('general.organisation_name', 'Nigeria Civil Aviation Authority');
        $this->logo_url = (string) setting('general.logo_url', '/images/ncaa-logo.png');
        $this->primary_color = (string) setting('general.primary_color', '#2a1770');
        $this->accent_color = (string) setting('general.accent_color', '#008751');
        $this->support_email = (string) setting('general.support_email', 'qa.support@ncaa.gov.ng');
        $this->timezone = (string) setting('general.timezone', 'Africa/Lagos');
        $this->date_format = (string) setting('general.date_format', 'M d, Y');

        // Audit rules
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

        // 1. Finding levels
        $service->set('audit.finding_levels', $this->finding_levels, 'audit', 'json');

        // 2. Alert days & frequencies
        $alertDaysArray = array_values(array_filter(array_map('intval', array_map('trim', explode(',', $this->routine_alert_days)))));
        $service->set('audit.routine_alert_days', $alertDaysArray, 'audit', 'json');

        $freqsArray = array_values(array_filter(array_map('trim', explode(',', $this->allowed_frequencies))));
        $service->set('audit.allowed_frequencies', $freqsArray, 'audit', 'json');

        // 3. Question default options & Non-compliant answers
        $optsArray = array_values(array_filter(array_map('trim', explode(',', $this->default_answer_options))));
        $service->set('audit.default_answer_options', $optsArray, 'audit', 'json');

        $nonCompArray = array_values(array_filter(array_map('trim', explode(',', $this->non_compliant_answers))));
        $service->set('audit.non_compliant_answers', $nonCompArray, 'audit', 'json');

        // 4. Reference formats
        $service->set('audit.reference_format.ncr', $this->reference_format_ncr, 'audit', 'string');
        $service->set('audit.reference_format.certificate', $this->reference_format_certificate, 'audit', 'string');
        $service->set('audit.reference_format.audit', $this->reference_format_audit, 'audit', 'string');

        // 5. Upload rules
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
                <flux:subheading size="lg">{{ __('Configure dynamic business parameters, branding identity, and statutory audit defaults') }}</flux:subheading>
            </div>
            <flux:button wire:click="resetToDefault('{{ $activeTab }}')" variant="outline" icon="arrow-path" class="text-xs">
                {{ __('Reset Group to Defaults') }}
            </flux:button>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-zinc-200 dark:border-zinc-800">
        <nav class="flex space-x-6 -mb-px" aria-label="Settings Group Tabs">
            <button
                type="button"
                wire:click="setTab('general')"
                class="py-3 px-1 text-sm font-semibold border-b-2 transition flex items-center gap-2 {{ $activeTab === 'general' ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
            >
                <flux:icon name="paint-brush" class="size-4" />
                <span>General &amp; Branding</span>
            </button>

            <button
                type="button"
                wire:click="setTab('audit')"
                class="py-3 px-1 text-sm font-semibold border-b-2 transition flex items-center gap-2 {{ $activeTab === 'audit' ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
            >
                <flux:icon name="clipboard-document-check" class="size-4" />
                <span>Audit Rules</span>
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
                <!-- System & Org Name -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input
                        wire:model="system_name"
                        :label="__('System Name')"
                        description="Appears on page titles, headers, and certificates"
                        required
                    />

                    <flux:input
                        wire:model="organisation_name"
                        :label="__('Organisation Legal Name')"
                        description="Official statutory body or authority name"
                        required
                    />
                </div>

                <!-- Support Email & Timezone -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input
                        wire:model="support_email"
                        :label="__('System Support Email')"
                        type="email"
                        description="Displayed for contact and troubleshooting"
                        required
                    />

                    <flux:input
                        wire:model="timezone"
                        :label="__('Timezone')"
                        description="Default timezone for date/time computations"
                        placeholder="Africa/Lagos"
                        required
                    />
                </div>

                <!-- Date Format -->
                <div class="max-w-xs">
                    <flux:input
                        wire:model="date_format"
                        :label="__('Display Date Format')"
                        description="PHP date format (e.g. M d, Y or Y-m-d)"
                        required
                    />
                </div>

                <!-- Colors & Theme Tokens -->
                <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 space-y-4">
                    <div>
                        <h4 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ __('Theme Colors (CSS Variables)') }}</h4>
                        <p class="text-xs text-zinc-500">{{ __('Changes feed directly into --ncaa-primary and --ncaa-accent tokens') }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <flux:input
                                wire:model.live="primary_color"
                                :label="__('Primary Color (Hex)')"
                                placeholder="#2a1770"
                                required
                            />
                            <div class="flex items-center gap-2 pt-1">
                                <span class="size-6 rounded-md border border-zinc-300 dark:border-zinc-700 shadow-xs" style="background-color: {{ $primary_color }}"></span>
                                <span class="text-xs font-mono text-zinc-500">Active Primary: {{ $primary_color }}</span>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <flux:input
                                wire:model.live="accent_color"
                                :label="__('Accent Color (Hex)')"
                                placeholder="#008751"
                                required
                            />
                            <div class="flex items-center gap-2 pt-1">
                                <span class="size-6 rounded-md border border-zinc-300 dark:border-zinc-700 shadow-xs" style="background-color: {{ $accent_color }}"></span>
                                <span class="text-xs font-mono text-zinc-500">Active Accent: {{ $accent_color }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Logo Management -->
                <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 space-y-3">
                    <div>
                        <h4 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ __('Official Logo') }}</h4>
                        <p class="text-xs text-zinc-500">{{ __('Rendered across topbars, mobile navigation, PDF certificates, and reports') }}</p>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-xl bg-white border border-zinc-200 dark:border-zinc-700 p-1 flex items-center justify-center shadow-xs">
                            @if($new_logo)
                                <img src="{{ $new_logo->temporaryUrl() }}" class="max-h-14 max-w-14 object-contain" alt="New Logo Preview">
                            @else
                                <img src="{{ $logo_url }}" class="max-h-14 max-w-14 object-contain" alt="Current Logo">
                            @endif
                        </div>

                        <div class="flex-1 space-y-1">
                            <input
                                type="file"
                                wire:model="new_logo"
                                accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                class="text-xs text-zinc-600 dark:text-zinc-300 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-zinc-100 dark:file:bg-zinc-800 file:text-zinc-700 dark:file:text-zinc-300 hover:file:bg-zinc-200 cursor-pointer"
                            />
                            <p class="text-[11px] text-zinc-400">PNG, JPG, SVG or WEBP (Max: 2MB). Current path: {{ $logo_url }}</p>
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex items-center gap-3">
                    <flux:button variant="primary" type="submit">
                        {{ __('Save General & Branding') }}
                    </flux:button>
                </div>
            </form>
        </div>
    @endif

    <!-- TAB 2: Audit Rules -->
    @if($activeTab === 'audit')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-xs space-y-8">
            <div>
                <flux:heading size="lg">{{ __('Audit Governance Rules & Parameters') }}</flux:heading>
                <flux:subheading>{{ __('Finding classification levels, CAP deadlines, numbering masks, and statutory upload limits') }}</flux:subheading>
            </div>

            <form wire:submit="saveAuditRules" class="space-y-8">
                <!-- 1. Finding Classification Levels -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                        <div>
                            <h4 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">1. Finding Levels &amp; Deadlines</h4>
                            <p class="text-xs text-zinc-500">Configure CAP submission and closure verification deadlines per severity</p>
                        </div>
                        <flux:button wire:click="addFindingLevel" size="xs" variant="outline" icon="plus">
                            Add Finding Level
                        </flux:button>
                    </div>

                    <div class="space-y-3">
                        @foreach($finding_levels as $i => $levelItem)
                            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/60 dark:bg-zinc-850/60 grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                                <div class="md:col-span-2">
                                    <flux:input
                                        wire:model="finding_levels.{{ $i }}.level"
                                        label="Level ID *"
                                        type="number"
                                        min="1"
                                        required
                                    />
                                </div>
                                <div class="md:col-span-4">
                                    <flux:input
                                        wire:model="finding_levels.{{ $i }}.name"
                                        label="Level Designation Title *"
                                        placeholder="e.g. Critical Safety"
                                        required
                                    />
                                </div>
                                <div class="md:col-span-2">
                                    <flux:input
                                        wire:model="finding_levels.{{ $i }}.cap_submission_days"
                                        label="CAP Submission (Days) *"
                                        type="number"
                                        min="1"
                                        required
                                    />
                                </div>
                                <div class="md:col-span-2">
                                    <flux:input
                                        wire:model="finding_levels.{{ $i }}.closure_days"
                                        label="Closure Due (Days) *"
                                        type="number"
                                        min="1"
                                        required
                                    />
                                </div>
                                <div class="md:col-span-2 flex justify-end pt-5">
                                    @if(count($finding_levels) > 1)
                                        <flux:button
                                            wire:click="removeFindingLevel({{ $i }})"
                                            size="sm"
                                            variant="ghost"
                                            icon="trash"
                                            class="text-rose-600 hover:text-rose-700"
                                            title="Delete level"
                                        />
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- 2. Alert Days & Allowed Frequencies -->
                <div class="space-y-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <div>
                        <h4 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">2. Routine Surveillance Scheduling</h4>
                        <p class="text-xs text-zinc-500">Advance reminder intervals and allowed frequencies for routine quality processes</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:input
                            wire:model="routine_alert_days"
                            label="Advance Notice Days (comma separated) *"
                            placeholder="14, 7"
                            description="Send notifications when an audit is exactly X days away"
                            required
                        />

                        <flux:input
                            wire:model="allowed_frequencies"
                            label="Permitted Frequencies (comma separated) *"
                            placeholder="weekly, monthly, quarterly, bi-annual, annual"
                            description="Available intervals when designing routine processes"
                            required
                        />
                    </div>
                </div>

                <!-- 3. Question Options & Non-compliant Definitions -->
                <div class="space-y-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <div>
                        <h4 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">3. Verification Question Options &amp; Non-Compliance</h4>
                        <p class="text-xs text-zinc-500">Default choice selections and automated triggers for recommending NCRs</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:input
                            wire:model="default_answer_options"
                            label="Default Answer Options (comma separated) *"
                            placeholder="Compliant, Non-compliant, N/A"
                            description="Pre-populated options for new radio/choice audit checklist questions"
                            required
                        />

                        <flux:input
                            wire:model="non_compliant_answers"
                            label="Non-Compliant Trigger Values (comma separated) *"
                            placeholder="Non-compliant, non_compliant"
                            description="Answers that automatically prompt inspectors or reviewers to log an NCR"
                            required
                        />
                    </div>
                </div>

                <!-- 4. Reference Number Formats -->
                <div class="space-y-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <div>
                        <h4 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">4. Reference Numbering Masks</h4>
                        <p class="text-xs text-zinc-500">Tokens supported: {YYYY}, {YY}, {MM}, {CODE}, {####}, {###}, {##}, {RAND}</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <flux:input
                            wire:model="reference_format_ncr"
                            label="NCR Mask *"
                            placeholder="NCR-{YYYY}-{####}"
                            required
                        />

                        <flux:input
                            wire:model="reference_format_certificate"
                            label="Certificate Mask *"
                            placeholder="DSD-QA-{YYYY}-{####}"
                            required
                        />

                        <flux:input
                            wire:model="reference_format_audit"
                            label="Audit Engagement Mask *"
                            placeholder="AUD-{YYYY}-{CODE}-{###}"
                            required
                        />
                    </div>
                </div>

                <!-- 5. Upload Limits & Permitted File Extensions -->
                <div class="space-y-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <div>
                        <h4 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">5. File Upload Whitelists &amp; Quotas</h4>
                        <p class="text-xs text-zinc-500">Control allowed formats and maximum payload sizes for security</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Evidence Uploads -->
                        <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 space-y-3 bg-zinc-50/50 dark:bg-zinc-850/40">
                            <h5 class="text-xs font-bold uppercase text-zinc-700 dark:text-zinc-300">Field Evidence &amp; CAP Attachments</h5>
                            <flux:input
                                wire:model="upload_evidence_types"
                                label="Permitted Extensions *"
                                placeholder="jpg,jpeg,png,webp,pdf,docx,doc"
                                required
                            />
                            <flux:input
                                wire:model="upload_evidence_max_kb"
                                label="Max Size in Kilobytes (KB) *"
                                type="number"
                                min="512"
                                max="102400"
                                description="e.g. 10240 KB = 10 MB"
                                required
                            />
                        </div>

                        <!-- Document Library -->
                        <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 space-y-3 bg-zinc-50/50 dark:bg-zinc-850/40">
                            <h5 class="text-xs font-bold uppercase text-zinc-700 dark:text-zinc-300">Regulatory Library Documents</h5>
                            <flux:input
                                wire:model="upload_document_types"
                                label="Permitted Extensions *"
                                placeholder="pdf,docx"
                                required
                            />
                            <flux:input
                                wire:model="upload_document_max_kb"
                                label="Max Size in Kilobytes (KB) *"
                                type="number"
                                min="512"
                                max="102400"
                                description="e.g. 20480 KB = 20 MB"
                                required
                            />
                        </div>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="pt-4 flex items-center gap-3">
                    <flux:button variant="primary" type="submit">
                        {{ __('Save Audit Rules') }}
                    </flux:button>
                </div>
            </form>
        </div>
    @endif
</div>
