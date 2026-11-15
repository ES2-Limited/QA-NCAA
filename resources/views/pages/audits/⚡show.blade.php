<?php

use App\Models\Audit;
use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Audit Overview')] class extends Component {
    public Audit $audit;

    public function mount($audit)
    {
        $this->audit = Audit::with([
            'process',
            'directorate',
            'leadAuditor',
            'reviewer',
            'auditors',
            'responses',
            'findings',
        ])->findOrFail($audit);

        $this->authorize('view', $this->audit);
    }

    public function with()
    {
        // Snapshot or fallback to process questions
        $questions = ! empty($this->audit->questions_snapshot)
            ? $this->audit->questions_snapshot
            : ($this->audit->process ? $this->audit->process->questions->toArray() : []);

        return [
            'audit' => $this->audit,
            'questions' => $questions,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-zinc-500 mb-1">
                <a href="{{ route('audits.index') }}" class="hover:underline" wire:navigate>Audits</a>
                <span>/</span>
                <span>{{ $audit->reference_number }}</span>
            </div>
            <div class="flex items-center space-x-3">
                <flux:heading size="xl">{{ $audit->title }}</flux:heading>
                @php
                    $badgeColor = match ($audit->status) {
                        'scheduled' => 'zinc',
                        'in_progress' => 'amber',
                        'submitted' => 'cyan',
                        'reviewed' => 'blue',
                        'head_approved' => 'indigo',
                        'director_approved' => 'emerald',
                        'returned' => 'rose',
                        default => 'zinc',
                    };
                @endphp
                <flux:badge size="sm" :color="$badgeColor">
                    {{ ucwords(str_replace('_', ' ', $audit->status)) }}
                </flux:badge>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <flux:button href="{{ route('audits.index') }}" variant="ghost" wire:navigate>
                &larr; Back to Audits
            </flux:button>
            @hasrole('field_auditor')
                <flux:button href="{{ route('field.audit', $audit->id) }}" variant="primary" icon="clipboard-document-check" wire:navigate>
                    Open Field Execution
                </flux:button>
            @endhasrole
        </div>
    </div>

    @if (session()->has('message'))
        <div class="p-3 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-800 rounded-xl text-sm">
            {{ session('message') }}
        </div>
    @endif

    <!-- Status Timeline -->
    <div class="p-5 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700">
        <flux:heading size="sm" class="mb-3">Audit Lifecycle Timeline</flux:heading>
        @php
            $stages = [
                'scheduled' => 'Scheduled',
                'in_progress' => 'In Progress',
                'submitted' => 'Submitted',
                'reviewed' => 'Reviewed',
                'head_approved' => 'Head Approved',
                'director_approved' => 'Director Approved',
            ];
            $currentStatus = $audit->status;
            $allKeys = array_keys($stages);
            $currentIndex = array_search($currentStatus, $allKeys);
            if ($currentIndex === false && $currentStatus === 'returned') {
                $currentIndex = 1;
            }
        @endphp

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2 pt-2">
            @foreach($stages as $key => $label)
                @php
                    $stageIndex = array_search($key, $allKeys);
                    $isPassed = $currentIndex !== false && $stageIndex <= $currentIndex;
                    $isCurrent = $key === $currentStatus;
                @endphp
                <div class="p-2.5 rounded-xl border text-center text-xs transition {{ $isCurrent ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-500 font-bold text-emerald-900 dark:text-emerald-200 shadow-sm' : ($isPassed ? 'bg-zinc-50 dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300' : 'bg-transparent border-dashed border-zinc-200 dark:border-zinc-750 text-zinc-400') }}">
                    <div class="text-[10px] uppercase font-mono tracking-wider {{ $isCurrent ? 'text-emerald-700 dark:text-emerald-400' : 'text-zinc-400' }}">Stage {{ $stageIndex + 1 }}</div>
                    <div class="mt-0.5 truncate">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Engagement Details & Team Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Overview -->
        <div class="md:col-span-2 p-5 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 space-y-4">
            <flux:heading size="md">Engagement Parameters</flux:heading>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                    <span class="font-semibold text-zinc-500 uppercase">Process Template:</span>
                    <div class="font-bold text-zinc-900 dark:text-zinc-100 text-sm mt-0.5">
                        @if($audit->process)
                            <a href="{{ route('processes.show', $audit->process->id) }}" class="text-emerald-600 dark:text-emerald-400 hover:underline" wire:navigate>
                                {{ $audit->process->name }} &rarr;
                            </a>
                        @else
                            Independent Run
                        @endif
                    </div>
                </div>

                <div>
                    <span class="font-semibold text-zinc-500 uppercase">Directorate / Station:</span>
                    <div class="font-bold text-zinc-900 dark:text-zinc-100 text-sm mt-0.5">
                        {{ $audit->directorate->code ?? 'NCAA Authority' }} &bull; {{ $audit->location }}
                    </div>
                </div>

                <div>
                    <span class="font-semibold text-zinc-500 uppercase">Scheduled Date:</span>
                    <div class="font-mono text-zinc-900 dark:text-zinc-100 font-bold mt-0.5">
                        {{ $audit->scheduled_for ? $audit->scheduled_for->format('l, M d, Y') : ($audit->start_date ? $audit->start_date->format('l, M d, Y') : '—') }}
                    </div>
                </div>

                <div>
                    <span class="font-semibold text-zinc-500 uppercase">Type & Compliance:</span>
                    <div class="font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">
                        {{ ucfirst($audit->type) }} &bull; <span class="text-emerald-600">{{ $audit->compliance_score }}% Compliance</span>
                    </div>
                </div>
            </div>

            @if($audit->scope)
                <div class="pt-3 border-t border-zinc-100 dark:border-zinc-700/60 text-xs">
                    <strong class="text-zinc-700 dark:text-zinc-300">Target Audit Scope:</strong>
                    <p class="text-zinc-600 dark:text-zinc-400 mt-0.5">{{ $audit->scope }}</p>
                </div>
            @endif
        </div>

        <!-- Assigned Team -->
        <div class="p-5 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 space-y-4">
            <flux:heading size="md">Assigned Team</flux:heading>

            <div class="space-y-3 text-xs">
                <div>
                    <span class="text-zinc-500 uppercase font-semibold text-[10px]">Lead Auditor:</span>
                    <div class="font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">
                        {{ $audit->leadAuditor->name ?? 'Lead Auditor' }}
                    </div>
                    <div class="text-zinc-500 text-[11px]">{{ $audit->leadAuditor->email ?? '' }}</div>
                </div>

                <div class="pt-2 border-t border-zinc-100 dark:border-zinc-700/60">
                    <span class="text-zinc-500 uppercase font-semibold text-[10px]">Quality Reviewer:</span>
                    <div class="font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">
                        {{ $audit->reviewer->name ?? 'None Assigned' }}
                    </div>
                    <div class="text-zinc-500 text-[11px]">{{ $audit->reviewer->email ?? 'Awaiting reviewer assignment' }}</div>
                </div>

                <div class="pt-2 border-t border-zinc-100 dark:border-zinc-700/60">
                    <span class="text-zinc-500 uppercase font-semibold text-[10px]">Field Auditor(s):</span>
                    @forelse($audit->auditors as $auditor)
                        <div class="font-medium text-zinc-800 dark:text-zinc-200 mt-1 flex items-center space-x-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span>{{ $auditor->name }}</span>
                        </div>
                    @empty
                        <div class="text-zinc-400 text-xs italic mt-0.5">No field auditors assigned yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Snapshotted Questions Checklist -->
    <div class="p-5 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="md">Checklist Questions ({{ count($questions) }})</flux:heading>
                <flux:subheading>Snapshotted at audit creation time</flux:subheading>
            </div>
            <flux:badge size="sm" color="zinc">Snapshot Protected</flux:badge>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse($questions as $q)
                <div class="py-3.5 space-y-1.5 first:pt-0 last:pb-0">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start space-x-2">
                            <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300">
                                #{{ $q['order'] ?? 1 }}
                            </span>
                            <div>
                                <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $q['question'] }}</h4>
                                @if(!empty($q['requirement_ref']))
                                    <span class="inline-block mt-1 text-[11px] font-mono text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 px-1.5 py-0.5 rounded border border-emerald-200 dark:border-emerald-800">
                                        {{ $q['requirement_ref'] }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center space-x-1.5 shrink-0">
                            <flux:badge size="sm" color="zinc">{{ ucfirst($q['type'] ?? 'radio') }}</flux:badge>
                            @if(($q['source'] ?? '') === 'ai')
                                <flux:badge size="sm" color="amber">AI</flux:badge>
                            @endif
                        </div>
                    </div>

                    @if(!empty($q['guidance']))
                        <div class="text-xs text-zinc-500 italic pl-7">
                            <strong>Guidance:</strong> {{ $q['guidance'] }}
                        </div>
                    @endif
                </div>
            @empty
                <div class="py-6 text-center text-xs text-zinc-500">
                    No questions recorded for this audit.
                </div>
            @endforelse
        </div>
    </div>
</div>
