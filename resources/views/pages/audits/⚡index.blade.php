<?php

use App\Models\Audit;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;

new #[Title('Quality Audits Registry')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function with()
    {
        $query = Audit::with(['process', 'directorate', 'leadAuditor', 'reviewer', 'auditors'])
            ->latest('id');

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('reference_number', 'like', '%' . $this->search . '%');
            });
        }

        if (! empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        return [
            'audits' => $query->paginate(12),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl">Audits Registry</flux:heading>
            <flux:subheading>Digital surveillance engagements, routine quality inspections, and assigned auditor teams</flux:subheading>
        </div>

        <div class="flex items-center space-x-2">
            <flux:button href="{{ route('processes.index') }}" variant="primary" icon="plus" wire:navigate>
                Schedule from Process
            </flux:button>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="p-3 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-800 rounded-xl text-sm">
            {{ session('message') }}
        </div>
    @endif

    <!-- Filters & Search -->
    <div class="p-4 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 flex flex-col sm:flex-row items-center gap-4 justify-between">
        <div class="w-full sm:w-80">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search audit title or ref..." icon="magnifying-glass" clearable />
        </div>

        <div class="w-full sm:w-auto flex flex-wrap items-center gap-2">
            <flux:select wire:model.live="statusFilter" class="w-48">
                <flux:select.option value="">All Statuses</flux:select.option>
                <flux:select.option value="scheduled">Scheduled</flux:select.option>
                <flux:select.option value="in_progress">In Progress</flux:select.option>
                <flux:select.option value="submitted">Submitted</flux:select.option>
                <flux:select.option value="reviewed">Reviewed</flux:select.option>
                <flux:select.option value="head_approved">Head Approved</flux:select.option>
                <flux:select.option value="director_approved">Director Approved</flux:select.option>
                <flux:select.option value="returned">Returned</flux:select.option>
            </flux:select>
        </div>
    </div>

    <!-- Audits Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 text-xs text-zinc-500 font-semibold uppercase">
                    <tr>
                        <th class="py-3 px-4">Audit Title / Ref</th>
                        <th class="py-3 px-4">Process Template</th>
                        <th class="py-3 px-4">Directorate / Unit</th>
                        <th class="py-3 px-4">Scheduled Date</th>
                        <th class="py-3 px-4">Team</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($audits as $audit)
                        <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-750 transition cursor-pointer" onclick="window.location='{{ route('audits.show', $audit->id) }}'">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-zinc-900 dark:text-zinc-100 hover:text-emerald-600 transition">
                                    {{ $audit->title }}
                                </div>
                                <div class="text-xs text-zinc-500 font-mono">
                                    {{ $audit->reference_number }}
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-xs font-medium text-zinc-700 dark:text-zinc-300">
                                {{ $audit->process->name ?? 'Custom Run' }}
                            </td>
                            <td class="py-3.5 px-4 text-xs text-zinc-600 dark:text-zinc-400">
                                <span class="font-bold">{{ $audit->directorate->code ?? 'DSD' }}</span>
                                @if($audit->process?->unit)
                                    <span class="text-zinc-400">&bull;</span> {{ $audit->process->unit->name }}
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs font-mono text-zinc-700 dark:text-zinc-300">
                                {{ $audit->scheduled_for ? $audit->scheduled_for->format('M d, Y') : ($audit->start_date ? $audit->start_date->format('M d, Y') : '—') }}
                            </td>
                            <td class="py-3.5 px-4 text-xs text-zinc-600 dark:text-zinc-400">
                                <div class="flex items-center space-x-1">
                                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">
                                        {{ $audit->leadAuditor->name ?? 'Lead Auditor' }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-zinc-500">
                                    {{ $audit->auditors->count() }} field auditor(s) &bull; {{ $audit->reviewer ? '1 reviewer' : 'No reviewer' }}
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
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
                            </td>
                            <td class="py-3.5 px-4 text-right" onclick="event.stopPropagation()">
                                <flux:button href="{{ route('audits.show', $audit->id) }}" size="xs" variant="ghost" icon="eye" wire:navigate>
                                    View
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500 text-xs">
                                No audits matching the selected filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($audits->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
                {{ $audits->links() }}
            </div>
        @endif
    </div>
</div>
