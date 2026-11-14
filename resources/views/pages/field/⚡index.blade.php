<?php

use App\Models\Audit;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts.field.mobile')] #[Title('My Audits — NCAA Field Auditor')] class extends Component {
    public string $tab = 'in_progress'; // upcoming, in_progress, submitted

    public function setTab(string $t)
    {
        $this->tab = $t;
    }

    public function with()
    {
        $user = auth()->user();

        $query = Audit::whereHas('auditors', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        })
        ->with(['process.unit', 'directorate', 'answers'])
        ->latest('id');

        $audits = match ($this->tab) {
            'upcoming' => (clone $query)->where('status', 'scheduled')->get(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->get(),
            'submitted' => (clone $query)->whereIn('status', ['submitted', 'reviewed', 'head_approved', 'director_approved', 'returned'])->get(),
            default => (clone $query)->get(),
        };

        return [
            'audits' => $audits,
        ];
    }
}; ?>

<div class="space-y-4">
    <!-- Welcome card -->
    <div class="p-4 bg-gradient-to-r from-emerald-800 to-emerald-950 text-white rounded-2xl shadow-sm space-y-1">
        <div class="text-[11px] font-semibold text-emerald-300 uppercase tracking-wider">Field Surveillance Hub</div>
        <h2 class="text-lg font-black">{{ auth()->user()->name }}</h2>
        <p class="text-xs text-emerald-100">
            Assigned to oversight and quality checks under NCAA Directorate of Special Duties.
        </p>
    </div>

    <!-- Tab switcher (big touch targets) -->
    <div class="grid grid-cols-3 gap-1 bg-zinc-200 dark:bg-zinc-900 p-1 rounded-xl">
        <button 
            wire:click="setTab('in_progress')" 
            class="py-2.5 text-xs font-bold rounded-lg transition {{ $tab === 'in_progress' ? 'bg-white dark:bg-zinc-800 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900' }}">
            In Progress
        </button>
        <button 
            wire:click="setTab('upcoming')" 
            class="py-2.5 text-xs font-bold rounded-lg transition {{ $tab === 'upcoming' ? 'bg-white dark:bg-zinc-800 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900' }}">
            Upcoming
        </button>
        <button 
            wire:click="setTab('submitted')" 
            class="py-2.5 text-xs font-bold rounded-lg transition {{ $tab === 'submitted' ? 'bg-white dark:bg-zinc-800 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900' }}">
            Submitted
        </button>
    </div>

    <!-- Audits Cards List -->
    <div class="space-y-3">
        @forelse($audits as $audit)
            @php
                $questionsCount = !empty($audit->questions_snapshot) ? count($audit->questions_snapshot) : ($audit->process ? $audit->process->questions()->count() : 0);
                $answeredCount = $audit->answers->count();
                $pct = $questionsCount > 0 ? (int) round(($answeredCount / $questionsCount) * 100) : 0;
            @endphp
            <a href="{{ route('field.audit', $audit->id) }}" wire:navigate class="block p-4 bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-sm hover:border-emerald-500 transition">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300">
                        {{ $audit->reference_number }}
                    </span>
                    @php
                        $color = match ($audit->status) {
                            'scheduled' => 'zinc',
                            'in_progress' => 'amber',
                            'submitted' => 'cyan',
                            'reviewed' => 'blue',
                            'head_approved' => 'emerald',
                            'director_approved' => 'emerald',
                            'returned' => 'rose',
                            default => 'zinc',
                        };
                    @endphp
                    <flux:badge size="sm" :color="$color">
                        {{ ucwords(str_replace('_', ' ', $audit->status)) }}
                    </flux:badge>
                </div>

                <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 leading-snug">
                    {{ $audit->title }}
                </h3>

                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <div>
                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $audit->directorate->code ?? 'DSD' }}</span>
                        @if($audit->process?->unit)
                            &bull; {{ $audit->process->unit->name }}
                        @endif
                    </div>
                    <div>
                        📅 {{ $audit->scheduled_for ? $audit->scheduled_for->format('M d, Y') : ($audit->start_date ? $audit->start_date->format('M d, Y') : 'Today') }}
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="text-zinc-500 font-medium">Progress</span>
                        <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $answeredCount }}/{{ $questionsCount }} answered ({{ $pct }}%)</span>
                    </div>
                    <div class="w-full bg-zinc-100 dark:bg-zinc-800 rounded-full h-2 overflow-hidden">
                        <div class="bg-emerald-500 h-2 rounded-full transition-all duration-300" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            </a>
        @empty
            <div class="p-8 text-center text-zinc-500 bg-white dark:bg-zinc-900 rounded-2xl border border-dashed border-zinc-300 dark:border-zinc-800">
                <div class="text-2xl mb-1">📋</div>
                <div class="text-sm font-bold text-zinc-700 dark:text-zinc-300">No {{ str_replace('_', ' ', $tab) }} audits</div>
                <p class="text-xs text-zinc-400 mt-0.5">Audits assigned to you by the Lead Auditor will appear here.</p>
            </div>
        @endforelse
    </div>
</div>
