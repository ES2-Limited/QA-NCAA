<?php

use Livewire\Component;

new class extends Component {
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
        $unreadCount = $user ? $user->unreadNotifications()->count() : 0;
        $notifications = $user ? $user->notifications()->take(10)->get() : collect();

        return [
            'unreadCount' => $unreadCount,
            'notifications' => $notifications,
        ];
    }
}; ?>

<div class="relative">
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" class="relative !p-2" aria-label="Notifications">
            <flux:icon name="bell" class="size-5 text-zinc-600 dark:text-zinc-400" />
            @if($unreadCount > 0)
                <span class="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white shadow-sm animate-pulse">
                    {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                </span>
            @endif
        </flux:button>

        <flux:menu class="w-80 md:w-96 p-0 divide-y divide-zinc-200 dark:divide-zinc-700 overflow-hidden">
            <div class="flex items-center justify-between p-3 bg-zinc-50 dark:bg-zinc-900/60">
                <div class="flex items-center space-x-2">
                    <span class="font-bold text-xs text-zinc-900 dark:text-zinc-100">Notifications</span>
                    @if($unreadCount > 0)
                        <flux:badge size="xs" color="rose">{{ $unreadCount }} new</flux:badge>
                    @endif
                </div>
                @if($unreadCount > 0)
                    <button wire:click="markAllAsRead" class="text-[11px] text-emerald-600 dark:text-emerald-400 hover:underline font-medium">
                        Mark all as read
                    </button>
                @endif
            </div>

            <div class="max-h-80 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse($notifications as $n)
                    @php
                        $data = $n->data;
                        $isUnread = $n->unread();
                        $url = $data['url'] ?? '#';
                    @endphp
                    <div class="p-3 hover:bg-zinc-50 dark:hover:bg-zinc-750/50 transition flex items-start justify-between gap-2 {{ $isUnread ? 'bg-emerald-50/40 dark:bg-emerald-950/20' : '' }}">
                        <a href="{{ $url }}" wire:click="markAsRead('{{ $n->id }}')" class="flex-1 space-y-1 block">
                            <div class="flex items-center space-x-1.5">
                                @if($isUnread)
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                                @endif
                                <span class="font-semibold text-xs text-zinc-900 dark:text-zinc-100 leading-snug">
                                    {{ $data['message'] ?? 'New notification' }}
                                </span>
                            </div>
                            <div class="text-[10px] text-zinc-400 pl-3.5">
                                {{ $n->created_at->diffForHumans() }}
                            </div>
                        </a>
                        @if($isUnread)
                            <button wire:click="markAsRead('{{ $n->id }}')" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 p-1" title="Mark as read">
                                <flux:icon name="check" class="size-3.5" />
                            </button>
                        @endif
                    </div>
                @empty
                    <div class="p-6 text-center text-xs text-zinc-500">
                        No notifications yet.
                    </div>
                @endforelse
            </div>
        </flux:menu>
    </flux:dropdown>
</div>
