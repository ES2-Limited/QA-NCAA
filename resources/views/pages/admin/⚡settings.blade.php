<?php

use App\Services\SettingService;
use Database\Seeders\SettingSeeder;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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

    // Group 2: Audit & CAP Policies
    public int $cap_due_level_1 = 7;
    public int $cap_due_level_2 = 14;
    public int $cap_due_level_3 = 30;
    public int $advance_reminder_days = 14;

    public function mount(): void
    {
        // Enforce system_admin role
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
        $this->cap_due_level_1 = (int) setting('audit.cap_due.level_1', 7);
        $this->cap_due_level_2 = (int) setting('audit.cap_due.level_2', 14);
        $this->cap_due_level_3 = (int) setting('audit.cap_due.level_3', 30);
        $this->advance_reminder_days = (int) setting('audit.advance_reminder_days', 14);
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

        // Process logo upload if present
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

    public function saveAuditPolicies(): void
    {
        $this->validate([
            'cap_due_level_1' => ['required', 'integer', 'min:1', 'max:365'],
            'cap_due_level_2' => ['required', 'integer', 'min:1', 'max:365'],
            'cap_due_level_3' => ['required', 'integer', 'min:1', 'max:365'],
            'advance_reminder_days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $service = app(SettingService::class);
        $service->set('audit.cap_due.level_1', $this->cap_due_level_1, 'audit', 'integer');
        $service->set('audit.cap_due.level_2', $this->cap_due_level_2, 'audit', 'integer');
        $service->set('audit.cap_due.level_3', $this->cap_due_level_3, 'audit', 'integer');
        $service->set('audit.advance_reminder_days', $this->advance_reminder_days, 'audit', 'integer');

        Flux::toast(variant: 'success', text: __('Audit & CAP policies saved successfully.'));
    }

    /**
     * Reset the active tab or all settings to system defaults.
     */
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
                <span>Audit &amp; CAP Policies</span>
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

                <!-- Action Button -->
                <div class="pt-4 flex items-center gap-3">
                    <flux:button variant="primary" type="submit">
                        {{ __('Save General & Branding') }}
                    </flux:button>
                </div>
            </form>
        </div>
    @endif

    <!-- TAB 2: Audit & CAP Policies -->
    @if($activeTab === 'audit')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-xs space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Statutory Audit & CAP Due Date Parameters') }}</flux:heading>
                <flux:subheading>{{ __('Non-conformance rectification schedules and advance notifications') }}</flux:subheading>
            </div>

            <form wire:submit="saveAuditPolicies" class="space-y-6 max-w-2xl">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <flux:input
                        wire:model="cap_due_level_1"
                        :label="__('Level 1 CAP Due (Days)')"
                        type="number"
                        min="1"
                        max="365"
                        description="Critical statutory safety finding"
                        required
                    />

                    <flux:input
                        wire:model="cap_due_level_2"
                        :label="__('Level 2 CAP Due (Days)')"
                        type="number"
                        min="1"
                        max="365"
                        description="Major operational finding"
                        required
                    />

                    <flux:input
                        wire:model="cap_due_level_3"
                        :label="__('Level 3 CAP Due (Days)')"
                        type="number"
                        min="1"
                        max="365"
                        description="Minor observation / administrative"
                        required
                    />
                </div>

                <div class="max-w-xs">
                    <flux:input
                        wire:model="advance_reminder_days"
                        :label="__('Routine Advance Notice (Days)')"
                        type="number"
                        min="1"
                        max="90"
                        description="Advance warning before routine audit execution"
                        required
                    />
                </div>

                <div class="pt-2">
                    <flux:button variant="primary" type="submit">
                        {{ __('Save Audit Policies') }}
                    </flux:button>
                </div>
            </form>
        </div>
    @endif
</div>
