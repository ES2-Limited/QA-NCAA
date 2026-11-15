<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Audit;
use App\Models\Finding;
use App\Models\Process;
use Flux\Flux;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Actions\DeletePasskey;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('User Profile & Settings — NCAA QA/IA')] class extends Component {
    use PasswordValidationRules;
    use ProfileValidationRules;

    // Active Tab: 'profile', 'security', 'notifications', 'appearance'
    public string $activeTab = 'profile';

    // Profile Details
    public string $name = '';
    public string $email = '';
    public string $phone = '';

    // Security - Password
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    // Security - Logout Other Devices
    public string $logout_password = '';

    // Security - Two Factor & Passkeys
    public bool $canManageTwoFactor = false;
    public bool $twoFactorEnabled = false;
    public bool $requiresConfirmation = false;

    #[Locked]
    public bool $canManagePasskeys = false;

    #[Locked]
    public array $passkeys = [];

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deletingPasskeyId = null;

    #[Locked]
    public string $deletingPasskeyName = '';

    // Notification Preferences
    public bool $notif_audit_assigned_email = true;
    public bool $notif_audit_assigned_inapp = true;
    public bool $notif_audit_submitted_email = true;
    public bool $notif_audit_submitted_inapp = true;
    public bool $notif_ncr_raised_email = true;
    public bool $notif_ncr_raised_inapp = true;
    public bool $notif_cap_submitted_email = true;
    public bool $notif_cap_submitted_inapp = true;
    public bool $notif_cap_reviewed_email = true;
    public bool $notif_cap_reviewed_inapp = true;
    public bool $notif_routine_audit_email = true;
    public bool $notif_routine_audit_inapp = true;

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $user = Auth::user();

        $this->name = $user->name ?? '';
        $this->email = $user->email ?? '';
        $this->phone = $user->phone ?? '';

        // Two-factor setup
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();
        if ($this->canManageTwoFactor) {
            if (Fortify::confirmsTwoFactorAuthentication() && is_null($user->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication($user);
            }

            $this->twoFactorEnabled = $user->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        // Passkeys
        $this->canManagePasskeys = Features::canManagePasskeys();
        if ($this->canManagePasskeys) {
            $this->loadPasskeys();
        }

        // Notification preferences
        $prefs = $user->notification_preferences ?? [];
        $this->notif_audit_assigned_email = (bool) ($prefs['audit_assigned']['email'] ?? true);
        $this->notif_audit_assigned_inapp = (bool) ($prefs['audit_assigned']['inapp'] ?? true);
        $this->notif_audit_submitted_email = (bool) ($prefs['audit_submitted']['email'] ?? true);
        $this->notif_audit_submitted_inapp = (bool) ($prefs['audit_submitted']['inapp'] ?? true);
        $this->notif_ncr_raised_email = (bool) ($prefs['ncr_raised']['email'] ?? true);
        $this->notif_ncr_raised_inapp = (bool) ($prefs['ncr_raised']['inapp'] ?? true);
        $this->notif_cap_submitted_email = (bool) ($prefs['cap_submitted']['email'] ?? true);
        $this->notif_cap_submitted_inapp = (bool) ($prefs['cap_submitted']['inapp'] ?? true);
        $this->notif_cap_reviewed_email = (bool) ($prefs['cap_reviewed']['email'] ?? true);
        $this->notif_cap_reviewed_inapp = (bool) ($prefs['cap_reviewed']['inapp'] ?? true);
        $this->notif_routine_audit_email = (bool) ($prefs['routine_audit']['email'] ?? true);
        $this->notif_routine_audit_inapp = (bool) ($prefs['routine_audit']['inapp'] ?? true);
    }

    /**
     * Switch current tab.
     */
    public function setTab(string $tab): void
    {
        if (in_array($tab, ['profile', 'security', 'notifications', 'appearance'])) {
            $this->activeTab = $tab;
        }
    }

    /**
     * Update the profile information for the authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        if (config('fortify.lowercase_usernames')) {
            $this->email = Str::lower($this->email);
        }

        $rules = $this->profileRules($user->id);
        $rules['phone'] = ['nullable', 'string', 'max:30'];

        $validated = $this->validate($rules);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated successfully.'));
    }

    /**
     * Send email verification notification.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();
        Session::flash('status', 'verification-link-sent');
        Flux::toast(variant: 'success', text: __('Verification link sent.'));
    }

    /**
     * Update user password.
     */
    public function updatePassword(): void
    {
        $validated = $this->validate([
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ]);

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('Password updated successfully.'));
    }

    /**
     * Log out of other browser sessions.
     */
    public function logoutOtherDevices(): void
    {
        $this->validate([
            'logout_password' => ['required', 'string'],
        ]);

        if (! Hash::check($this->logout_password, Auth::user()->password)) {
            $this->addError('logout_password', __('The provided password does not match your current password.'));

            return;
        }

        Auth::logoutOtherDevices($this->logout_password);

        $this->reset('logout_password');

        Flux::toast(variant: 'success', text: __('Other device sessions have been logged out.'));
    }

    /**
     * Save notification preferences.
     */
    public function updateNotificationPreferences(): void
    {
        $user = Auth::user();

        $preferences = [
            'audit_assigned' => [
                'email' => $this->notif_audit_assigned_email,
                'inapp' => $this->notif_audit_assigned_inapp,
            ],
            'audit_submitted' => [
                'email' => $this->notif_audit_submitted_email,
                'inapp' => $this->notif_audit_submitted_inapp,
            ],
            'ncr_raised' => [
                'email' => $this->notif_ncr_raised_email,
                'inapp' => $this->notif_ncr_raised_inapp,
            ],
            'cap_submitted' => [
                'email' => $this->notif_cap_submitted_email,
                'inapp' => $this->notif_cap_submitted_inapp,
            ],
            'cap_reviewed' => [
                'email' => $this->notif_cap_reviewed_email,
                'inapp' => $this->notif_cap_reviewed_inapp,
            ],
            'routine_audit' => [
                'email' => $this->notif_routine_audit_email,
                'inapp' => $this->notif_routine_audit_inapp,
            ],
        ];

        $user->update([
            'notification_preferences' => $preferences,
        ]);

        Flux::toast(variant: 'success', text: __('Notification preferences saved.'));
    }

    /**
     * Load passkeys.
     */
    public function loadPasskeys(): void
    {
        $this->passkeys = Auth::user()->passkeys()
            ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
            ->latest()
            ->get()
            ->map(fn ($passkey) => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                'created_at_diff' => $passkey->created_at->diffForHumans(),
                'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
            ])
            ->toArray();
    }

    public function confirmDelete(int $passkeyId): void
    {
        $passkey = Auth::user()->passkeys()->findOrFail($passkeyId);
        $this->deletingPasskeyId = $passkey->id;
        $this->deletingPasskeyName = $passkey->name;
        $this->showDeleteModal = true;
    }

    public function deletePasskey(DeletePasskey $deletePasskey): void
    {
        if (! $this->deletingPasskeyId) {
            return;
        }

        $passkey = Auth::user()->passkeys()->findOrFail($this->deletingPasskeyId);
        $deletePasskey(Auth::user(), $passkey);

        $this->closeDeleteModal();
        $this->loadPasskeys();
        Flux::toast(variant: 'success', text: __('Passkey removed.'));
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingPasskeyId = null;
        $this->deletingPasskeyName = '';
    }

    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
    }

    public function disableTwoFactor(DisableTwoFactorAuthentication $disable): void
    {
        $disable(Auth::user());
        $this->twoFactorEnabled = false;
        Flux::toast(variant: 'success', text: __('Two-factor authentication disabled.'));
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }

    /**
     * Compute Role-specific operational summary metrics and actions.
     */
    #[Computed]
    public function roleSummary(): array
    {
        $user = Auth::user();
        if (! $user) {
            return [];
        }

        $items = [];

        // Lead Auditor
        if ($user->hasRole('lead_auditor') || $user->hasRole('system_admin')) {
            $createdProcesses = Process::where('created_by', $user->id)->count();
            if ($createdProcesses === 0 && $user->hasRole('lead_auditor')) {
                $createdProcesses = Process::count();
            }

            $auditsScheduled = Audit::where('lead_auditor_id', $user->id)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->count();

            $items[] = [
                'role' => 'Lead Auditor',
                'badge' => 'Operational',
                'stats' => [
                    [
                        'label' => 'Processes Managed',
                        'count' => $createdProcesses,
                        'url' => route('processes.index'),
                        'icon' => 'document-duplicate',
                    ],
                    [
                        'label' => 'Audits Scheduled / Active',
                        'count' => $auditsScheduled,
                        'url' => route('audits.index'),
                        'icon' => 'clipboard-document-check',
                    ],
                ],
            ];
        }

        // Reviewer
        if ($user->hasRole('reviewer') || $user->hasRole('system_admin')) {
            $awaitingReview = Audit::where('status', 'submitted')->count();

            $items[] = [
                'role' => 'Technical Reviewer',
                'badge' => 'Quality Review',
                'stats' => [
                    [
                        'label' => 'Audits Awaiting Review',
                        'count' => $awaitingReview,
                        'url' => route('review.index'),
                        'icon' => 'magnifying-glass',
                    ],
                ],
            ];
        }

        // Auditee
        if ($user->hasRole('auditee') || $user->hasRole('system_admin')) {
            $openNcrs = Finding::where('status', 'open')->count();

            $items[] = [
                'role' => 'Auditee / Process Owner',
                'badge' => 'Compliance',
                'stats' => [
                    [
                        'label' => 'Open Non-Conformance Reports (NCRs)',
                        'count' => $openNcrs,
                        'url' => route('ncrs.index'),
                        'icon' => 'exclamation-triangle',
                    ],
                ],
            ];
        }

        // Director / Head of Audit
        if ($user->hasRole('director') || $user->hasRole('head_of_audit') || $user->hasRole('system_admin')) {
            $awaitingApproval = Audit::where('status', 'reviewed')->count();

            $items[] = [
                'role' => 'Executive Director / Head of Audit',
                'badge' => 'Executive Governance',
                'stats' => [
                    [
                        'label' => 'Items Awaiting Final Approval',
                        'count' => $awaitingApproval,
                        'url' => route('approvals.index'),
                        'icon' => 'check-badge',
                    ],
                ],
            ];
        }

        return $items;
    }

    public function with(): array
    {
        $user = Auth::user();

        // Browser session metadata
        $sessions = [];
        if (config('session.driver') === 'database') {
            $sessions = DB::table('sessions')
                ->where('user_id', $user->id)
                ->orderBy('last_activity', 'desc')
                ->take(5)
                ->get()
                ->map(fn ($s) => [
                    'ip' => $s->ip_address,
                    'agent' => $s->user_agent,
                    'is_current' => $s->id === Session::getId(),
                    'last_active' => Carbon::createFromTimestamp($s->last_activity)->diffForHumans(),
                ])
                ->toArray();
        }

        return [
            'user' => $user,
            'sessions' => $sessions,
            'directorateName' => $user?->directorate?->name ?? 'Directorate of Special Duties (DSD)',
            'departmentName' => $user?->dept?->name ?? ($user?->department ?: 'Quality Assurance & Internal Audit Department'),
            'unitName' => $user?->unit?->name ?? 'Internal Audit Execution Unit',
        ];
    }
}; ?>

<div class="space-y-6 w-full max-w-5xl mx-auto pb-12">
    <!-- Breadcrumb & Page Title -->
    <div>
        <nav class="flex items-center text-xs text-zinc-500 mb-1 space-x-1.5" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200 transition">Dashboard</a>
            <span>/</span>
            <span class="text-zinc-800 dark:text-zinc-200 font-medium">Settings</span>
            <span>/</span>
            <span class="text-emerald-700 dark:text-emerald-400 font-semibold">User Profile</span>
        </nav>
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Auditor Profile & Settings') }}</flux:heading>
                <flux:subheading size="lg">{{ __('Manage NCAA user identity, authentication safeguards, and alerting channels') }}</flux:subheading>
            </div>
        </div>
    </div>

    <!-- 1. Header Card: Avatar, Full Name, Staff ID, Roles, Org Hierarchy -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-xs relative overflow-hidden">
        <div class="absolute top-0 right-0 w-96 h-96 bg-emerald-500/5 rounded-full blur-3xl pointer-events-none -mr-20 -mt-20"></div>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
            <div class="flex items-start sm:items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-emerald-700 text-white font-bold text-xl flex items-center justify-center shrink-0 shadow-md ring-4 ring-emerald-50 dark:ring-emerald-950/40">
                    {{ $user->initials() }}
                </div>
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">{{ $user->name }}</h2>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-mono font-semibold bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">
                            {{ $user->staff_id ?? 'NCAA-STAFF-'.str_pad((string)$user->id, 4, '0', STR_PAD_LEFT) }}
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                        @forelse($user->roles as $role)
                            <flux:badge size="sm" color="zinc" class="capitalize">
                                {{ str_replace('_', ' ', $role->name) }}
                            </flux:badge>
                        @empty
                            <flux:badge size="sm" color="zinc">Civil Aviation Personnel</flux:badge>
                        @endforelse
                    </div>

                    <p class="text-xs text-zinc-500 dark:text-zinc-400 flex items-center gap-1.5 pt-1">
                        <flux:icon name="envelope" class="size-3.5 text-zinc-400" />
                        <span>{{ $user->email }}</span>
                        @if($user->phone)
                            <span class="opacity-40">&bull;</span>
                            <flux:icon name="phone" class="size-3.5 text-zinc-400" />
                            <span>{{ $user->phone }}</span>
                        @endif
                    </p>
                </div>
            </div>

            <!-- Organization Hierarchy Path -->
            <div class="bg-zinc-50 dark:bg-zinc-800/60 rounded-xl p-3.5 border border-zinc-200/70 dark:border-zinc-800 text-xs space-y-1.5 min-w-[280px]">
                <div class="text-[10px] font-bold tracking-wider uppercase text-emerald-700 dark:text-emerald-400">Official Directorate Unit</div>
                <div class="flex items-center text-zinc-700 dark:text-zinc-300 font-medium">
                    <span class="truncate">{{ $directorateName }}</span>
                </div>
                <div class="flex items-center text-zinc-500 text-[11px] pl-2 border-l-2 border-emerald-500/50 space-x-1.5">
                    <span>&rarr;</span>
                    <span class="truncate">{{ $departmentName }}</span>
                </div>
                <div class="flex items-center text-zinc-500 text-[11px] pl-4 border-l-2 border-emerald-500/20 space-x-1.5">
                    <span>&rarr;</span>
                    <span class="truncate font-semibold text-zinc-800 dark:text-zinc-200">{{ $unitName }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Role Summary Panel -->
    @if(count($this->roleSummary) > 0)
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
                <div class="flex items-center space-x-2">
                    <flux:icon name="chart-bar" class="size-5 text-emerald-600 dark:text-emerald-400" />
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">Role Responsibilities &amp; Operational Queue</h3>
                </div>
                <span class="text-xs text-zinc-500">Live operational metrics linked to your assignments</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach($this->roleSummary as $roleGroup)
                    @foreach($roleGroup['stats'] as $st)
                        <a href="{{ $st['url'] }}" wire:navigate class="group p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/60 dark:bg-zinc-800/40 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/20 hover:border-emerald-300 dark:hover:border-emerald-800/60 transition flex items-center justify-between">
                            <div class="space-y-1 min-w-0 pr-2">
                                <span class="text-[11px] font-semibold text-zinc-500 group-hover:text-emerald-700 dark:group-hover:text-emerald-400 transition block truncate">
                                    {{ $st['label'] }}
                                </span>
                                <div class="text-2xl font-black text-zinc-900 dark:text-zinc-100 group-hover:text-emerald-700 dark:group-hover:text-emerald-300 transition">
                                    {{ $st['count'] }}
                                </div>
                                <span class="text-[10px] text-zinc-400 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 font-medium inline-flex items-center gap-0.5">
                                    View open items &rarr;
                                </span>
                            </div>
                            <div class="size-10 rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center shrink-0 group-hover:border-emerald-400 dark:group-hover:border-emerald-700 transition">
                                <flux:icon :name="$st['icon']" class="size-5 text-zinc-600 dark:text-zinc-300 group-hover:text-emerald-600 transition" />
                            </div>
                        </a>
                    @endforeach
                @endforeach
            </div>
        </div>
    @endif

    <!-- 3. Navigation Tabs -->
    <div class="border-b border-zinc-200 dark:border-zinc-800">
        <nav class="flex space-x-6 -mb-px" aria-label="Settings Tabs">
            <button
                type="button"
                wire:click="setTab('profile')"
                class="py-3 px-1 text-sm font-semibold border-b-2 transition flex items-center gap-2 {{ $activeTab === 'profile' ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
            >
                <flux:icon name="user" class="size-4" />
                <span>Profile Details</span>
            </button>

            <button
                type="button"
                wire:click="setTab('security')"
                class="py-3 px-1 text-sm font-semibold border-b-2 transition flex items-center gap-2 {{ $activeTab === 'security' ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
            >
                <flux:icon name="shield-check" class="size-4" />
                <span>Security</span>
            </button>

            <button
                type="button"
                wire:click="setTab('notifications')"
                class="py-3 px-1 text-sm font-semibold border-b-2 transition flex items-center gap-2 {{ $activeTab === 'notifications' ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
            >
                <flux:icon name="bell" class="size-4" />
                <span>Notifications</span>
            </button>

            <button
                type="button"
                wire:click="setTab('appearance')"
                class="py-3 px-1 text-sm font-semibold border-b-2 transition flex items-center gap-2 {{ $activeTab === 'appearance' ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
            >
                <flux:icon name="paint-brush" class="size-4" />
                <span>Appearance</span>
            </button>
        </nav>
    </div>

    <!-- TAB 1: Profile Details -->
    @if($activeTab === 'profile')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-xs space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Personal Identity & Contact Information') }}</flux:heading>
                <flux:subheading>{{ __('Keep your communication records up-to-date for audit scheduling and alerts') }}</flux:subheading>
            </div>

            <form wire:submit="updateProfileInformation" class="space-y-4 max-w-xl">
                <flux:input
                    wire:model="name"
                    :label="__('Full Name')"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    data-test="profile-name"
                />

                <div>
                    <flux:input
                        wire:model="email"
                        :label="__('Official Email Address')"
                        type="email"
                        required
                        autocomplete="email"
                        data-test="profile-email"
                    />

                    @if ($this->hasUnverifiedEmail)
                        <div class="mt-3 p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900 text-xs text-amber-800 dark:text-amber-200">
                            {{ __('Your email address is unverified.') }}
                            <flux:link class="font-semibold underline ml-1 cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to send verification link.') }}
                            </flux:link>

                            @if (session('status') === 'verification-link-sent')
                                <div class="mt-1 font-bold text-emerald-600 dark:text-emerald-400">
                                    {{ __('A new verification link has been sent to your email address.') }}
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <flux:input
                    wire:model="phone"
                    :label="__('Telephone / Extension Number')"
                    type="text"
                    placeholder="+234 800 000 0000"
                    autocomplete="tel"
                />

                <div class="pt-2">
                    <flux:button variant="primary" type="submit" data-test="update-profile-button">
                        {{ __('Save Changes') }}
                    </flux:button>
                </div>
            </form>

            @if ($this->showDeleteUser)
                <div class="pt-6 border-t border-zinc-100 dark:border-zinc-800 max-w-xl">
                    <livewire:pages::settings.delete-user-form />
                </div>
            @endif
        </div>
    @endif

    <!-- TAB 2: Security (Password, 2FA, Passkeys, Active Sessions) -->
    @if($activeTab === 'security')
        <div class="space-y-6">
            <!-- Password Update -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-xs space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('Update Password') }}</flux:heading>
                    <flux:subheading>{{ __('Ensure your account is using a long, random password to stay secure') }}</flux:subheading>
                </div>

                <form wire:submit="updatePassword" class="space-y-4 max-w-xl">
                    <flux:input
                        wire:model="current_password"
                        :label="__('Current Password')"
                        type="password"
                        required
                        autocomplete="current-password"
                        viewable
                    />

                    <flux:input
                        wire:model="password"
                        :label="__('New Password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        viewable
                    />

                    <flux:input
                        wire:model="password_confirmation"
                        :label="__('Confirm New Password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        viewable
                    />

                    <div class="pt-2">
                        <flux:button variant="primary" type="submit" data-test="update-password-button">
                            {{ __('Update Password') }}
                        </flux:button>
                    </div>
                </form>
            </div>

            <!-- Two-Factor Authentication -->
            @if ($canManageTwoFactor)
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-xs space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('Two-Factor Authentication (2FA)') }}</flux:heading>
                        <flux:subheading>{{ __('Add an additional layer of verification with your authenticator mobile application') }}</flux:subheading>
                    </div>

                    <div class="max-w-xl">
                        @if ($twoFactorEnabled)
                            <div class="space-y-4">
                                <div class="flex items-center gap-2 text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                                    <flux:icon name="check-circle" class="size-4" />
                                    <span>Two-factor authentication is active on your account.</span>
                                </div>

                                <flux:button variant="danger" wire:click="disableTwoFactor">
                                    {{ __('Disable 2FA') }}
                                </flux:button>

                                <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                            </div>
                        @else
                            <div class="space-y-4">
                                <flux:text variant="subtle">
                                    {{ __('When two-factor authentication is enabled, you will be prompted for a secure pin code during login retrieved from a TOTP-supported authenticator app.') }}
                                </flux:text>

                                <flux:modal.trigger name="two-factor-setup-modal">
                                    <flux:button variant="primary" wire:click="$dispatch('start-two-factor-setup')">
                                        {{ __('Enable 2FA') }}
                                    </flux:button>
                                </flux:modal.trigger>

                                <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Passkeys (Passwordless) -->
            @if ($canManagePasskeys)
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-xs space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('Hardware Passkeys') }}</flux:heading>
                        <flux:subheading>{{ __('Sign in without passwords using Touch ID, Windows Hello, or FIDO2 security keys') }}</flux:subheading>
                    </div>

                    <div class="max-w-xl space-y-4">
                        <div class="border rounded-xl border-zinc-200 dark:border-zinc-800 overflow-hidden divide-y divide-zinc-200 dark:divide-zinc-800">
                            @forelse ($passkeys as $passkey)
                                <div class="flex items-center justify-between p-3.5 bg-zinc-50/50 dark:bg-zinc-800/40">
                                    <div class="flex items-center gap-3">
                                        <div class="size-9 rounded-xl bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center shrink-0">
                                            <flux:icon name="key" class="size-4 text-zinc-600 dark:text-zinc-300" />
                                        </div>
                                        <div>
                                            <p class="font-bold text-xs text-zinc-900 dark:text-zinc-100">{{ $passkey['name'] }}</p>
                                            <p class="text-[11px] text-zinc-500">
                                                Added {{ $passkey['created_at_diff'] }}
                                                @if ($passkey['last_used_at_diff'])
                                                    &bull; Used {{ $passkey['last_used_at_diff'] }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>

                                    <flux:button
                                        variant="ghost"
                                        size="xs"
                                        icon="trash"
                                        wire:click="confirmDelete({{ $passkey['id'] }})"
                                        class="text-rose-600 hover:text-rose-700"
                                    />
                                </div>
                            @empty
                                <div class="p-6 text-center text-xs text-zinc-500">
                                    <flux:icon name="key" class="size-6 mx-auto mb-1.5 opacity-40" />
                                    <p class="font-medium">No passkeys registered yet</p>
                                    <p class="text-[11px] text-zinc-400 mt-0.5">Register a security key for instantaneous authentication</p>
                                </div>
                            @endforelse
                        </div>

                        <x-passkey-registration />
                    </div>
                </div>
            @endif

            <!-- Active Sessions & Log Out Other Devices -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-xs space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Active Browser Sessions') }}</flux:heading>
                    <flux:subheading>{{ __('Manage and log out active sessions on other browsers, laptops, or mobile tablets') }}</flux:subheading>
                </div>

                <div class="max-w-xl space-y-4">
                    @if(count($sessions) > 0)
                        <div class="space-y-2">
                            @foreach($sessions as $s)
                                <div class="p-3 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/30 flex items-center justify-between text-xs">
                                    <div class="flex items-center space-x-3">
                                        <flux:icon name="computer-desktop" class="size-5 text-zinc-400" />
                                        <div>
                                            <p class="font-semibold text-zinc-800 dark:text-zinc-200">
                                                {{ $s['ip'] ?? '127.0.0.1' }}
                                                @if($s['is_current'])
                                                    <span class="ml-2 inline-flex items-center px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">This Device</span>
                                                @endif
                                            </p>
                                            <p class="text-[11px] text-zinc-500 truncate max-w-xs">{{ $s['agent'] }}</p>
                                        </div>
                                    </div>
                                    <span class="text-zinc-400 text-[11px]">{{ $s['last_active'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/40 text-xs text-zinc-600 dark:text-zinc-300 flex items-center gap-3">
                            <flux:icon name="device-phone-mobile" class="size-5 text-emerald-600 shrink-0" />
                            <div>
                                <span class="font-bold text-zinc-900 dark:text-zinc-100">Current authenticated session</span>
                                <p class="text-[11px] text-zinc-500">Your session is active from {{ request()->ip() }}.</p>
                            </div>
                        </div>
                    @endif

                    <!-- Logout Other Devices Form -->
                    <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800 space-y-3">
                        <flux:text variant="subtle" class="text-xs">
                            {{ __('If necessary, you may log out of all of your other browser sessions across all devices. Please enter your password to confirm.') }}
                        </flux:text>

                        <form wire:submit="logoutOtherDevices" class="space-y-3">
                            <flux:input
                                wire:model="logout_password"
                                type="password"
                                placeholder="{{ __('Enter current password to verify') }}"
                                required
                                viewable
                            />

                            <flux:button variant="danger" type="submit" icon="arrow-right-start-on-rectangle">
                                {{ __('Log Out Other Devices') }}
                            </flux:button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Passkey Modal -->
        <flux:modal
            name="delete-passkey-modal"
            class="max-w-md md:min-w-md"
            @close="closeDeleteModal"
            wire:model="showDeleteModal"
        >
            <div class="space-y-6">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Remove Passkey') }}</flux:heading>
                    <flux:text>
                        {{ __('Are you sure you want to remove the passkey ":name"? You will no longer be able to use it to sign in.', ['name' => $deletingPasskeyName]) }}
                    </flux:text>
                </div>

                <div class="flex gap-3 justify-end">
                    <flux:button variant="outline" wire:click="closeDeleteModal">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="danger" wire:click="deletePasskey">
                        {{ __('Remove Passkey') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    <!-- TAB 3: Notifications Preferences -->
    @if($activeTab === 'notifications')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-xs space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Notification Channels & Event Preferences') }}</flux:heading>
                <flux:subheading>{{ __('Configure which operational updates trigger email dispatches and internal application alerts') }}</flux:subheading>
            </div>

            <form wire:submit="updateNotificationPreferences" class="space-y-6 max-w-2xl">
                <div class="divide-y divide-zinc-200 dark:divide-zinc-800 border-y border-zinc-200 dark:border-zinc-800">
                    <!-- 1. Audit Assigned -->
                    <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="space-y-0.5">
                            <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Audit Assignment</span>
                            <p class="text-xs text-zinc-500">Alerted when you are assigned as lead auditor or team inspector</p>
                        </div>
                        <div class="flex items-center gap-6">
                            <flux:checkbox wire:model="notif_audit_assigned_email" label="Email" />
                            <flux:checkbox wire:model="notif_audit_assigned_inapp" label="In-App" />
                        </div>
                    </div>

                    <!-- 2. Audit Submitted -->
                    <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="space-y-0.5">
                            <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Audit Submitted for Review</span>
                            <p class="text-xs text-zinc-500">Notification when field responses are complete and ready for review</p>
                        </div>
                        <div class="flex items-center gap-6">
                            <flux:checkbox wire:model="notif_audit_submitted_email" label="Email" />
                            <flux:checkbox wire:model="notif_audit_submitted_inapp" label="In-App" />
                        </div>
                    </div>

                    <!-- 3. NCR Raised -->
                    <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="space-y-0.5">
                            <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Non-Conformance Report (NCR) Issued</span>
                            <p class="text-xs text-zinc-500">Alert when a Level 1, 2, or 3 finding is logged against your process</p>
                        </div>
                        <div class="flex items-center gap-6">
                            <flux:checkbox wire:model="notif_ncr_raised_email" label="Email" />
                            <flux:checkbox wire:model="notif_ncr_raised_inapp" label="In-App" />
                        </div>
                    </div>

                    <!-- 4. CAP Submitted -->
                    <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="space-y-0.5">
                            <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Corrective Action Plan (CAP) Uploaded</span>
                            <p class="text-xs text-zinc-500">Notification when an auditee submits root-cause mitigation evidence</p>
                        </div>
                        <div class="flex items-center gap-6">
                            <flux:checkbox wire:model="notif_cap_submitted_email" label="Email" />
                            <flux:checkbox wire:model="notif_cap_submitted_inapp" label="In-App" />
                        </div>
                    </div>

                    <!-- 5. CAP Reviewed -->
                    <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="space-y-0.5">
                            <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">CAP Review &amp; Closure Decision</span>
                            <p class="text-xs text-zinc-500">Status update when a CAP is approved or rejected by QA review</p>
                        </div>
                        <div class="flex items-center gap-6">
                            <flux:checkbox wire:model="notif_cap_reviewed_email" label="Email" />
                            <flux:checkbox wire:model="notif_cap_reviewed_inapp" label="In-App" />
                        </div>
                    </div>

                    <!-- 6. Routine Audit Due -->
                    <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="space-y-0.5">
                            <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Routine Audit Reminders</span>
                            <p class="text-xs text-zinc-500">14-day and 7-day advance warnings for statutory periodic audits</p>
                        </div>
                        <div class="flex items-center gap-6">
                            <flux:checkbox wire:model="notif_routine_audit_email" label="Email" />
                            <flux:checkbox wire:model="notif_routine_audit_inapp" label="In-App" />
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <flux:button variant="primary" type="submit" data-test="save-notifications-button">
                        {{ __('Save Notification Preferences') }}
                    </flux:button>
                </div>
            </form>
        </div>
    @endif

    <!-- TAB 4: Appearance -->
    @if($activeTab === 'appearance')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-xs space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Visual Theme & Appearance') }}</flux:heading>
                <flux:subheading>{{ __('Customize the visual mode of the NCAA QA/IA application on this browser') }}</flux:subheading>
            </div>

            <div class="max-w-md">
                <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                    <flux:radio value="light" icon="sun">{{ __('Light Mode') }}</flux:radio>
                    <flux:radio value="dark" icon="moon">{{ __('Dark Mode') }}</flux:radio>
                    <flux:radio value="system" icon="computer-desktop">{{ __('System Sync') }}</flux:radio>
                </flux:radio.group>
            </div>
        </div>
    @endif
</div>
