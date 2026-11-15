<?php

use App\Models\Notification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.field.mobile')] #[Title('Notifications — NCAA Field Auditor')] class extends Component {
    public function markAsRead(string $id)
    {
        $notification = auth()->user()?->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }
    }

    public function markAllAsRead()
    {
        auth()->user()?->unreadNotifications->markAsRead();
    }

    public function with()
    {
        $user = auth()->user();
        $notifications = $user ? $user->notifications()->take(30)->get() : collect();
        $unreadCount = $user ? $user->unreadNotifications()->count() : 0;

        return [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ];
    }
}; ?>

<div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Alerts &amp; Notifications</h1>
            <p class="text-xs text-zinc-500">Tap any notification to open the audit engagement</p>
        </div>
        @if($unreadCount > 0)
            <flux:button wire:click="markAllAsRead" size="xs" variant="ghost">
                Mark all as read
            </flux:button>
        @endif
    </div>

    <!-- Notification List -->
    <div class="space-y-2">
        @forelse($notifications as $n)
            @php
                $data = $n->data;
                $isUnread = $n->unread();
                // For field auditors, ensure audit link always points to /field/audits/{id}
                $auditId = $data['audit_id'] ?? null;
                $targetUrl = $auditId ? route('field.audit', $auditId) : ($data['url'] ?? route('field.index'));
            @endphp
            <div class="p-3.5 rounded-xl border transition {{ $isUnread ? 'bg-white dark:bg-zinc-900 border-emerald-300 dark:border-emerald-800 shadow-xs' : 'bg-white/60 dark:bg-zinc-900/40 border-zinc-200 dark:border-zinc-800' }}">
                <div class="flex items-start justify-between gap-3">
                    <a href="{{ $targetUrl }}" wire:click="markAsRead('{{ $n->id }}')" wire:navigate class="flex-1 space-y-1 block">
                        <div class="flex items-center space-x-2">
                            @if($isUnread)
                                <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                            @endif
                            <span class="font-semibold text-xs text-zinc-900 dark:text-zinc-100 leading-snug">
                                {{ $data['message'] ?? 'New notification' }}
                            </span>
                        </div>
                        <div class="text-[11px] text-zinc-400 pl-4 flex items-center justify-between">
                            <span>{{ $n->created_at->diffForHumans() }}</span>
                            <span class="text-emerald-600 dark:text-emerald-400 font-medium">Open Audit &rarr;</span>
                        </div>
                    </a>

                    @if($isUnread)
                        <button wire:click="markAsRead('{{ $n->id }}')" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 p-1 rounded-md" aria-label="Mark notification as read">
                            <flux:icon name="check" class="size-4" />
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="py-12 text-center text-zinc-500 text-xs">
                <flux:icon name="bell-slash" class="size-8 mx-auto mb-2 opacity-40" />
                <p>No notifications yet.</p>
            </div>
        @endforelse
    </div>
</div>
