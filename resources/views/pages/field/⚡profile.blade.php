<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.field.mobile')] #[Title('Auditor Profile — NCAA')] class extends Component {
    use PasswordValidationRules;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the field auditor.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('Password updated successfully.'));
    }

    /**
     * Log out the current user.
     */
    public function logout(Logout $logout)
    {
        return $logout();
    }

    public function with(): array
    {
        $user = Auth::user();

        return [
            'user' => $user,
            'directorate' => $user?->directorate?->name ?? 'Directorate of Standard & Regulations',
            'department' => $user?->dept?->name ?? ($user?->department ?: 'Airworthiness & Safety Standards'),
            'unit' => $user?->unit?->name ?? 'Field Inspection & Audit Unit',
        ];
    }
}; ?>

<div class="space-y-5 pb-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Auditor Profile</h1>
            <p class="text-xs text-zinc-500">Field Auditor credentials &amp; assignment details</p>
        </div>
        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
            Field Auditor
        </span>
    </div>

    <!-- User Information Card -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs space-y-3">
        <div class="flex items-center space-x-3 pb-3 border-b border-zinc-100 dark:border-zinc-800">
            <div class="w-12 h-12 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center text-base shadow-inner">
                {{ $user->initials() }}
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="font-bold text-sm text-zinc-900 dark:text-zinc-100 truncate">{{ $user->name }}</h2>
                <p class="text-xs text-zinc-500 truncate">{{ $user->email }}</p>
                <div class="mt-1 flex items-center gap-2">
                    <span class="text-[10px] font-mono text-zinc-400">ID: {{ $user->staff_id ?? 'NCAA-FA-'.str_pad((string)$user->id, 4, '0', STR_PAD_LEFT) }}</span>
                </div>
            </div>
        </div>

        <dl class="grid grid-cols-1 gap-2 text-xs">
            <div class="flex justify-between py-1 border-b border-zinc-50 dark:border-zinc-800/50">
                <dt class="text-zinc-500">Directorate</dt>
                <dd class="font-medium text-zinc-800 dark:text-zinc-200 text-right truncate max-w-[200px]">{{ $directorate }}</dd>
            </div>
            <div class="flex justify-between py-1 border-b border-zinc-50 dark:border-zinc-800/50">
                <dt class="text-zinc-500">Department</dt>
                <dd class="font-medium text-zinc-800 dark:text-zinc-200 text-right truncate max-w-[200px]">{{ $department }}</dd>
            </div>
            <div class="flex justify-between py-1">
                <dt class="text-zinc-500">Unit</dt>
                <dd class="font-medium text-zinc-800 dark:text-zinc-200 text-right truncate max-w-[200px]">{{ $unit }}</dd>
            </div>
        </dl>
    </div>

    <!-- Password Change Form -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs space-y-4">
        <div>
            <h2 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">Change Password</h2>
            <p class="text-xs text-zinc-500">Update your security passkey/password for field access</p>
        </div>

        <form wire:submit="updatePassword" class="space-y-3">
            <flux:input
                wire:model="current_password"
                type="password"
                label="{{ __('Current Password') }}"
                placeholder="Enter current password"
                required
                viewable
            />

            <flux:input
                wire:model="password"
                type="password"
                label="{{ __('New Password') }}"
                placeholder="Minimum 8 characters"
                required
                viewable
            />

            <flux:input
                wire:model="password_confirmation"
                type="password"
                label="{{ __('Confirm New Password') }}"
                placeholder="Re-enter new password"
                required
                viewable
            />

            <div class="pt-1">
                <flux:button type="submit" variant="primary" class="w-full justify-center">
                    {{ __('Update Password') }}
                </flux:button>
            </div>
        </form>
    </div>

    <!-- Session & Logout -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs space-y-3">
        <div>
            <h2 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">Active Session</h2>
            <p class="text-xs text-zinc-500">Sign out of this mobile auditing terminal</p>
        </div>

        <flux:button wire:click="logout" variant="danger" icon="arrow-right-start-on-rectangle" class="w-full justify-center">
            {{ __('Log Out of Field App') }}
        </flux:button>
    </div>
</div>
