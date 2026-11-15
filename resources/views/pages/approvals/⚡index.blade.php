<?php

use App\Models\Audit;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;

new #[Title('Executive Approvals')] class extends Component {
    use WithPagination;

    public function with()
    {
        $user = auth()->user();
        $workflow = app(\App\Services\WorkflowService::class);
        $steps = $workflow->getSteps();

        $query = Audit::with(['process.unit', 'directorate', 'leadAuditor', 'findings', 'certificate'])
            ->latest('id');

        if (! $user->can('settings.manage')) {
            // Find which steps user is eligible to act on or view
            $userActionStatuses = [];
            foreach ($steps as $st) {
                if ($workflow->userCanActOnStep($user, $st)) {
                    // Previous step status that feeds into this step
                    $prev = $workflow->getPreviousStep($st);
                    $feedingStatus = $prev ? $prev->action_status : 'submitted';
                    $userActionStatuses[] = $feedingStatus;
                    $userActionStatuses[] = $st->action_status;
                }
            }
            $userActionStatuses = array_unique($userActionStatuses);

            if (! empty($userActionStatuses)) {
                $query->whereIn('status', $userActionStatuses);
            }
        }

        return [
            'audits' => $query->paginate(10),
            'steps' => $steps,
            'isDirector' => $user->can('approvals.final'),
            'isHead' => $user->can('approvals.endorse'),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl">Executive Audit Approvals</flux:heading>
            <flux:subheading>
                @if($isDirector)
                    Final Directorate Certification & Sign-off Queue
                @else
                    Head of Audit Executive Review & Forwarding Queue
                @endif
            </flux:subheading>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="p-3 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-800 rounded-xl text-sm">
            {{ session('message') }}
        </div>
    @endif

    <!-- Audits Approval Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 text-xs text-zinc-500 font-semibold uppercase">
                    <tr>
                        <th class="py-3 px-4">Audit Title / Ref</th>
                        <th class="py-3 px-4">Directorate / Unit</th>
                        <th class="py-3 px-4">Compliance & NCRs</th>
                        <th class="py-3 px-4">Current Stage</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($audits as $audit)
                        <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-750 transition cursor-pointer" onclick="window.location='{{ route('approvals.show', $audit->id) }}'">
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
                            <td class="py-3.5 px-4 text-xs">
                                <div class="font-semibold text-zinc-800 dark:text-zinc-200">
                                    {{ $audit->compliance_score }}% Overall Conformance
                                </div>
                                <div class="text-[11px] text-zinc-500">
                                    {{ $audit->findings->count() }} NCR(s) &bull; {{ $audit->findings->where('status', 'open')->count() }} open
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                @php
                                    $color = match ($audit->status) {
                                        'reviewed' => 'blue',
                                        'head_approved' => 'indigo',
                                        'director_approved' => 'emerald',
                                        default => 'zinc',
                                    };
                                @endphp
                                <flux:badge size="sm" :color="$color">
                                    {{ ucwords(str_replace('_', ' ', $audit->status)) }}
                                </flux:badge>
                            </td>
                            <td class="py-3.5 px-4 text-right" onclick="event.stopPropagation()">
                                <flux:button href="{{ route('approvals.show', $audit->id) }}" size="xs" variant="primary" icon="check-badge" wire:navigate>
                                    Review & Decide
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-zinc-500 text-xs">
                                No audit engagements currently in your approval queue.
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
