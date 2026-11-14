<?php

use App\Models\Audit;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;

new #[Title('Audits Awaiting Review')] class extends Component {
    use WithPagination;

    public function with()
    {
        $user = auth()->user();

        $query = Audit::where('reviewer_id', $user->id)
            ->whereIn('status', ['submitted', 'in_progress', 'returned', 'reviewed'])
            ->with(['process.unit', 'directorate', 'leadAuditor', 'auditors', 'findings'])
            ->latest('id');

        return [
            'audits' => $query->paginate(10),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl">Quality Review Portal</flux:heading>
            <flux:subheading>Audits submitted by field teams awaiting technical review, non-conformance logging, or forwarding to Head of Audit</flux:subheading>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="p-3 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-800 rounded-xl text-sm">
            {{ session('message') }}
        </div>
    @endif

    <!-- Audits List -->
    <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 text-xs text-zinc-500 font-semibold uppercase">
                    <tr>
                        <th class="py-3 px-4">Audit Title / Ref</th>
                        <th class="py-3 px-4">Directorate / Unit</th>
                        <th class="py-3 px-4">Field Team</th>
                        <th class="py-3 px-4">Submitted Date</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($audits as $audit)
                        <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-750 transition cursor-pointer" onclick="window.location='{{ route('review.show', $audit->id) }}'">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-zinc-900 dark:text-zinc-100 hover:text-emerald-600 transition">
                                    {{ $audit->title }}
                                </div>
                                <div class="text-xs text-zinc-500 font-mono">
                                    {{ $audit->reference_number }}
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-zinc-600 dark:text-zinc-400">
                                <span class="font-bold">{{ $audit->directorate->code ?? 'DSD' }}</span>
                                @if($audit->process?->unit)
                                    &bull; {{ $audit->process->unit->name }}
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs text-zinc-600 dark:text-zinc-400">
                                <div class="font-semibold text-zinc-800 dark:text-zinc-200">
                                    {{ $audit->leadAuditor->name ?? 'Lead' }}
                                </div>
                                <div class="text-[11px] text-zinc-500">
                                    {{ $audit->auditors->count() }} field auditor(s)
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-xs font-mono text-zinc-700 dark:text-zinc-300">
                                {{ $audit->submitted_at ? $audit->submitted_at->format('M d, Y H:i') : 'In progress' }}
                            </td>
                            <td class="py-3.5 px-4">
                                @php
                                    $color = match ($audit->status) {
                                        'submitted' => 'cyan',
                                        'in_progress' => 'amber',
                                        'reviewed' => 'blue',
                                        'returned' => 'rose',
                                        default => 'zinc',
                                    };
                                @endphp
                                <flux:badge size="sm" :color="$color">
                                    {{ ucwords(str_replace('_', ' ', $audit->status)) }}
                                </flux:badge>
                            </td>
                            <td class="py-3.5 px-4 text-right" onclick="event.stopPropagation()">
                                <flux:button href="{{ route('review.show', $audit->id) }}" size="xs" variant="primary" icon="magnifying-glass" wire:navigate>
                                    Review Audit
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-zinc-500 text-xs">
                                No audits currently assigned to your review queue.
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
