<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

        @if(app()->environment('local', 'testing'))
            <div class="p-4 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-950/30 space-y-3" x-data="{
                fillCredentials(email) {
                    let emailInput = document.querySelector('input[name=email]');
                    let passInput = document.querySelector('input[name=password]');
                    if (emailInput) {
                        emailInput.value = email;
                        emailInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    if (passInput) {
                        passInput.value = 'password';
                        passInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                }
            }">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">
                        ⚡ Quick Demo Accounts
                    </span>
                    <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-mono">Password: password</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 text-xs">
                    @php
                        $demoLogins = [
                            ['role' => 'System Admin', 'email' => 'system_admin@ncaa.gov.ng', 'badge' => 'Admin'],
                            ['role' => 'Lead Auditor', 'email' => 'lead_auditor@ncaa.gov.ng', 'badge' => 'Audit Lead'],
                            ['role' => 'Field Auditor', 'email' => 'field_auditor@ncaa.gov.ng', 'badge' => 'Mobile Field'],
                            ['role' => 'QA Reviewer', 'email' => 'reviewer@ncaa.gov.ng', 'badge' => 'Reviewer'],
                            ['role' => 'Head of Audit', 'email' => 'head_of_audit@ncaa.gov.ng', 'badge' => 'Approver'],
                            ['role' => 'Director (DSD)', 'email' => 'director@ncaa.gov.ng', 'badge' => 'Sign-off'],
                            ['role' => 'Auditee (Unit)', 'email' => 'auditee@ncaa.gov.ng', 'badge' => 'CAP Submitter'],
                        ];
                    @endphp
                    @foreach($demoLogins as $demo)
                        <button
                            type="button"
                            x-on:click="fillCredentials('{{ $demo['email'] }}')"
                            class="text-left p-2 rounded-lg bg-white dark:bg-zinc-900 border border-emerald-100 dark:border-emerald-900/60 hover:border-emerald-500 hover:bg-emerald-100/50 dark:hover:bg-emerald-900/40 transition flex flex-col justify-between group shadow-2xs"
                        >
                            <div class="flex items-center justify-between w-full">
                                <span class="font-bold text-zinc-900 dark:text-zinc-100 text-[11px] group-hover:text-emerald-700 dark:group-hover:text-emerald-300">
                                    {{ $demo['role'] }}
                                </span>
                                <span class="text-[9px] font-mono px-1 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                                    {{ $demo['badge'] }}
                                </span>
                            </div>
                            <span class="text-[10px] text-zinc-500 truncate font-mono mt-0.5">
                                {{ $demo['email'] }}
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Don\'t have an account?') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
