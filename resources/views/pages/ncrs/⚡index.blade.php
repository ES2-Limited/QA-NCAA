<?php

use App\Models\Finding;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Title('Non-Conformance Reports (NCR)')] class extends Component {
    use WithPagination;

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $directorateFilter = '';

    #[Url]
    public string $overdueFilter = '';

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedDirectorateFilter()
    {
        $this->resetPage();
    }

    public function updatedOverdueFilter()
    {
        $this->resetPage();
    }

    public function with()
    {
        $user = auth()->user();

        $query = Finding::with(['audit.directorate', 'unit.department', 'cap', 'raisedBy'])
            ->latest('id');

        // Role scoping: role auditee sees NCRs for their assigned unit
        if ($user->hasRole('auditee')) {
            $query->where('assigned_unit_id', $user->unit_id);
        }

        if (! empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        if (! empty($this->directorateFilter)) {
            $dirId = $this->directorateFilter;
            $query->where(function ($q) use ($dirId) {
                $q->whereHas('audit', fn ($aq) => $aq->where('directorate_id', $dirId))
                  ->orWhereHas('unit.department', fn ($dq) => $dq->where('directorate_id', $dirId));
            });
        }

        if ($this->overdueFilter === '1') {
            $query->where('status', 'open')
                ->where(function ($q) {
                    $q->where('cap_due_at', '<', now())
                      ->orWhere('due_date', '<', now());
                });
        }

        return [
            'ncrs' => $query->paginate(12),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl">Non-Conformance Reports (NCR & CAP)</flux:heading>
            <flux:subheading>Formal deficiency tracking, root cause investigations, and corrective action closure workflows</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:select wire:model.live="statusFilter" class="w-48">
                <flux:select.option value="">All Statuses</flux:select.option>
                <flux:select.option value="open">Open (Action Pending)</flux:select.option>
                <flux:select.option value="cap_submitted">CAP Submitted</flux:select.option>
                <flux:select.option value="cap_accepted">CAP Accepted</flux:select.option>
                <flux:select.option value="cap_rejected">CAP Rejected</flux:select.option>
                <flux:select.option value="closed">Closed</flux:select.option>
            </flux:select>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="p-3 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-800 rounded-xl text-sm">
            {{ session('message') }}
        </div>
    @endif

    <!-- NCR Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 text-xs text-zinc-500 font-semibold uppercase">
                    <tr>
                        <th class="py-3 px-4">NCR Ref / Level</th>
                        <th class="py-3 px-4">Audit Engagement</th>
                        <th class="py-3 px-4">Assigned Unit</th>
                        <th class="py-3 px-4">Requirement Clause</th>
                        <th class="py-3 px-4">CAP Due Date</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($ncrs as $ncr)
                        @php
                            $isOverdue = $ncr->isOverdue();
                        @endphp
                        <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-750 transition cursor-pointer" onclick="window.location='{{ route('ncrs.show', $ncr->id) }}'">
                            <td class="py-3.5 px-4">
                                <div class="flex items-center space-x-2">
                                    <span class="font-bold text-zinc-900 dark:text-zinc-100 font-mono">
                                        {{ $ncr->reference ?: $ncr->finding_number }}
                                    </span>
                                    <flux:badge size="xs" :color="$ncr->level == 1 ? 'rose' : 'amber'">
                                        Level {{ $ncr->level }}
                                    </flux:badge>
                                </div>
                                <div class="text-xs text-zinc-500 truncate max-w-xs mt-0.5">
                                    {{ Str::limit($ncr->description ?: $ncr->condition, 50) }}
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $ncr->audit->title }}</div>
                                <div class="text-[11px] text-zinc-500">{{ $ncr->audit->reference_number }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-zinc-700 dark:text-zinc-300">
                                {{ $ncr->unit->name ?? 'Unassigned Unit' }}
                            </td>
                            <td class="py-3.5 px-4 text-xs font-mono text-emerald-600 dark:text-emerald-400">
                                {{ $ncr->requirement_ref ?: $ncr->criteria }}
                            </td>
                            <td class="py-3.5 px-4 text-xs font-mono">
                                @if($ncr->cap_due_at)
                                    <span class="{{ $isOverdue ? 'text-rose-600 font-bold' : 'text-zinc-700 dark:text-zinc-300' }}">
                                        {{ $ncr->cap_due_at->format('M d, Y H:i') }}
                                    </span>
                                    @if($isOverdue)
                                        <flux:badge size="xs" color="rose" class="block w-fit mt-0.5 animate-pulse">OVERDUE</flux:badge>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                @php
                                    $badgeColor = match ($ncr->status) {
                                        'open' => 'rose',
                                        'cap_submitted' => 'cyan',
                                        'cap_accepted' => 'emerald',
                                        'cap_rejected' => 'amber',
                                        'closed' => 'zinc',
                                        default => 'zinc',
                                    };
                                @endphp
                                <flux:badge size="sm" :color="$badgeColor">
                                    {{ ucwords(str_replace('_', ' ', $ncr->status)) }}
                                </flux:badge>
                            </td>
                            <td class="py-3.5 px-4 text-right" onclick="event.stopPropagation()">
                                <flux:button href="{{ route('ncrs.show', $ncr->id) }}" size="xs" variant="primary" icon="document-text" wire:navigate>
                                    Manage CAP
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500 text-xs">
                                No Non-Conformance Reports matching the current filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($ncrs->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
                {{ $ncrs->links() }}
            </div>
        @endif
    </div>
</div>
