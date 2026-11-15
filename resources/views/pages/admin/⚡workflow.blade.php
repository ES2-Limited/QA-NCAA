<?php

use App\Models\WorkflowStep;
use Database\Seeders\WorkflowStepSeeder;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

new #[Title('Approval Workflow Configuration — NCAA Admin')] class extends Component {
    public array $steps = [];
    public array $availableRoles = [];

    // Modal state for Add/Edit
    public bool $showModal = false;
    public ?int $editingStepId = null;
    public string $stepName = '';
    public string $stepRole = 'reviewer';
    public string $stepActionStatus = 'reviewed';
    public bool $stepCanReturn = true;
    public bool $stepRequired = true;
    public bool $stepIsFinal = false;

    public function mount(): void
    {
        if (! auth()->user()?->can('workflow.manage') && ! auth()->user()?->can('settings.manage')) {
            abort(403, 'Unauthorized.');
        }

        $this->availableRoles = Role::pluck('name')->toArray();
        $this->loadSteps();
    }

    public function loadSteps(): void
    {
        $this->steps = WorkflowStep::ordered()->get()->toArray();

        // If empty, auto-seed defaults
        if (empty($this->steps)) {
            (new WorkflowStepSeeder)->run();
            $this->steps = WorkflowStep::ordered()->get()->toArray();
        }
    }

    public function openAddModal(): void
    {
        $this->editingStepId = null;
        $this->stepName = '';
        $this->stepRole = 'reviewer';
        $this->stepActionStatus = 'step_' . (count($this->steps) + 1) . '_approved';
        $this->stepCanReturn = true;
        $this->stepRequired = true;
        $this->stepIsFinal = false;
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $step = WorkflowStep::findOrFail($id);
        $this->editingStepId = $step->id;
        $this->stepName = $step->name;
        $this->stepRole = $step->role;
        $this->stepActionStatus = $step->action_status;
        $this->stepCanReturn = (bool) $step->can_return;
        $this->stepRequired = (bool) $step->required;
        $this->stepIsFinal = (bool) $step->is_final;
        $this->showModal = true;
    }

    public function saveStep(): void
    {
        $this->validate([
            'stepName' => ['required', 'string', 'max:255'],
            'stepRole' => ['required', 'string', 'max:100'],
            'stepActionStatus' => ['required', 'string', 'max:100'],
        ]);

        if ($this->stepIsFinal) {
            // Unmark any previous final steps
            WorkflowStep::where('id', '!=', $this->editingStepId)->update(['is_final' => false]);
        }

        if ($this->editingStepId) {
            $step = WorkflowStep::findOrFail($this->editingStepId);
            $step->update([
                'name' => $this->stepName,
                'role' => $this->stepRole,
                'action_status' => $this->stepActionStatus,
                'can_return' => $this->stepCanReturn,
                'required' => $this->stepRequired,
                'is_final' => $this->stepIsFinal,
            ]);
            Flux::toast(variant: 'success', text: __('Workflow step updated.'));
        } else {
            $maxOrder = WorkflowStep::max('order') ?? 0;
            WorkflowStep::create([
                'order' => $maxOrder + 1,
                'name' => $this->stepName,
                'role' => $this->stepRole,
                'action_status' => $this->stepActionStatus,
                'can_return' => $this->stepCanReturn,
                'required' => $this->stepRequired,
                'is_final' => $this->stepIsFinal,
            ]);
            Flux::toast(variant: 'success', text: __('New workflow step added.'));
        }

        $this->showModal = false;
        $this->loadSteps();
    }

    public function moveUp(int $id): void
    {
        $step = WorkflowStep::findOrFail($id);
        $prev = WorkflowStep::where('order', '<', $step->order)->orderBy('order', 'desc')->first();

        if ($prev) {
            $prevOrder = $prev->order;
            $prev->update(['order' => $step->order]);
            $step->update(['order' => $prevOrder]);
        }

        $this->loadSteps();
        Flux::toast(variant: 'success', text: __('Step moved up.'));
    }

    public function moveDown(int $id): void
    {
        $step = WorkflowStep::findOrFail($id);
        $next = WorkflowStep::where('order', '>', $step->order)->orderBy('order', 'asc')->first();

        if ($next) {
            $nextOrder = $next->order;
            $next->update(['order' => $step->order]);
            $step->update(['order' => $nextOrder]);
        }

        $this->loadSteps();
        Flux::toast(variant: 'success', text: __('Step moved down.'));
    }

    public function markAsFinal(int $id): void
    {
        WorkflowStep::query()->update(['is_final' => false]);
        WorkflowStep::where('id', $id)->update(['is_final' => true]);

        $this->loadSteps();
        Flux::toast(variant: 'success', text: __('Marked as final certification step.'));
    }

    public function deleteStep(int $id): void
    {
        if (count($this->steps) <= 1) {
            Flux::toast(variant: 'danger', text: __('At least one workflow step is required.'));
            return;
        }

        WorkflowStep::findOrFail($id)->delete();

        // Re-order remaining steps
        $ordered = WorkflowStep::ordered()->get();
        foreach ($ordered as $idx => $st) {
            $st->update(['order' => $idx + 1]);
        }

        // If no final step exists, make the last one final
        if (! WorkflowStep::where('is_final', true)->exists()) {
            WorkflowStep::ordered()->get()->last()?->update(['is_final' => true]);
        }

        $this->loadSteps();
        Flux::toast(variant: 'success', text: __('Step removed.'));
    }

    public function resetToDefaults(): void
    {
        WorkflowStep::truncate();
        (new WorkflowStepSeeder)->run();
        $this->loadSteps();

        Flux::toast(variant: 'success', text: __('Workflow reset to standard 3-tier chain.'));
    }
}; ?>

<div class="space-y-6 w-full max-w-5xl mx-auto pb-12">
    <!-- Breadcrumb & Header -->
    <div>
        <nav class="flex items-center text-xs text-zinc-500 mb-1 space-x-1.5" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200 transition">Dashboard</a>
            <span>/</span>
            <span class="text-zinc-800 dark:text-zinc-200 font-medium">Administration</span>
            <span>/</span>
            <span class="text-emerald-700 dark:text-emerald-400 font-semibold">Approval Workflow</span>
        </nav>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Configurable Audit Approval Workflow') }}</flux:heading>
                <flux:subheading size="lg">{{ __('Define the sequence of review and governance tiers required before statutory certification') }}</flux:subheading>
            </div>
            <div class="flex items-center gap-2">
                <flux:button wire:click="resetToDefaults" variant="outline" icon="arrow-path" class="text-xs">
                    {{ __('Reset to Default 3-Step') }}
                </flux:button>
                <flux:button wire:click="openAddModal" variant="primary" icon="plus" class="text-xs">
                    {{ __('Add Workflow Tier') }}
                </flux:button>
            </div>
        </div>
    </div>

    <!-- Active Chain Visualization -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800">
            <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                <flux:icon name="arrows-right-left" class="size-4 text-emerald-600" />
                <span>Configured Governance Sequence ({{ count($steps) }} Tiers)</span>
            </h3>
            <span class="text-xs text-zinc-500">Left-to-right progression towards final certification</span>
        </div>

        <div class="flex flex-wrap items-center gap-2 pt-2">
            @foreach($steps as $idx => $st)
                <div class="flex items-center gap-2">
                    <div class="p-3 rounded-xl border {{ $st['is_final'] ? 'border-emerald-500/80 bg-emerald-50/50 dark:bg-emerald-950/20' : 'border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/40' }} text-xs space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="size-5 rounded-full bg-zinc-200 dark:bg-zinc-700 font-bold flex items-center justify-center text-[10px]">
                                {{ $idx + 1 }}
                            </span>
                            <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $st['name'] }}</span>
                            @if($st['is_final'])
                                <flux:badge size="xs" color="emerald">Final Certification</flux:badge>
                            @endif
                        </div>
                        <div class="text-[11px] text-zinc-500 pl-7 flex items-center gap-2">
                            <span>Role: <strong class="text-zinc-700 dark:text-zinc-300 font-mono">{{ $st['role'] }}</strong></span>
                            <span>&bull;</span>
                            <span>Status: <strong class="text-zinc-700 dark:text-zinc-300 font-mono">{{ $st['action_status'] }}</strong></span>
                        </div>
                    </div>

                    @if(! $loop->last)
                        <flux:icon name="arrow-right" class="size-4 text-zinc-400" />
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- Steps Management Table -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden shadow-xs">
        <table class="w-full text-left text-xs divide-y divide-zinc-200 dark:divide-zinc-800">
            <thead class="bg-zinc-50 dark:bg-zinc-850 text-zinc-500 font-semibold uppercase text-[10px] tracking-wider">
                <tr>
                    <th class="py-3 px-4 w-12 text-center">#</th>
                    <th class="py-3 px-4">Step Name</th>
                    <th class="py-3 px-4">Authorized Role</th>
                    <th class="py-3 px-4">Achieved Status</th>
                    <th class="py-3 px-4">Can Return?</th>
                    <th class="py-3 px-4">Final Issuance?</th>
                    <th class="py-3 px-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach($steps as $idx => $st)
                    <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-850/40 transition">
                        <td class="py-3.5 px-4 text-center font-bold font-mono text-zinc-500">
                            {{ $st['order'] }}
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">{{ $st['name'] }}</span>
                        </td>
                        <td class="py-3.5 px-4 font-mono font-semibold text-zinc-700 dark:text-zinc-300">
                            <flux:badge size="xs" color="zinc">{{ $st['role'] }}</flux:badge>
                        </td>
                        <td class="py-3.5 px-4 font-mono text-zinc-600 dark:text-zinc-400">
                            {{ $st['action_status'] }}
                        </td>
                        <td class="py-3.5 px-4">
                            @if($st['can_return'])
                                <span class="inline-flex items-center gap-1 text-emerald-600 font-semibold">
                                    <flux:icon name="check" class="size-3.5" /> Yes
                                </span>
                            @else
                                <span class="text-zinc-400 font-medium">No</span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4">
                            @if($st['is_final'])
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                    Issues Certificate
                                </span>
                            @else
                                <flux:button wire:click="markAsFinal({{ $st['id'] }})" size="xs" variant="ghost" class="text-[11px] text-zinc-500 hover:text-emerald-600">
                                    Set as Final
                                </flux:button>
                            @endif
                        </td>
                        <td class="py-3.5 px-4 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <!-- Re-order buttons -->
                                <flux:button wire:click="moveUp({{ $st['id'] }})" size="xs" variant="ghost" icon="chevron-up" title="Move Up" :disabled="$loop->first" />
                                <flux:button wire:click="moveDown({{ $st['id'] }})" size="xs" variant="ghost" icon="chevron-down" title="Move Down" :disabled="$loop->last" />

                                <!-- Edit & Delete -->
                                <flux:button wire:click="openEditModal({{ $st['id'] }})" size="xs" variant="outline" icon="pencil" title="Edit" />
                                <flux:button wire:click="deleteStep({{ $st['id'] }})" size="xs" variant="ghost" icon="trash" class="text-rose-600 hover:text-rose-700" title="Delete" />
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Modal for Add / Edit Step -->
    <flux:modal name="step-modal" wire:model="showModal" class="max-w-lg">
        <form wire:submit="saveStep" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingStepId ? __('Edit Workflow Tier') : __('Add Approval Tier') }}</flux:heading>
                <flux:subheading>{{ __('Configure step title, operational role authorization, and state transition') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input
                    wire:model="stepName"
                    label="Tier Name *"
                    placeholder="e.g. Directorate Technical Sign-off"
                    required
                />

                <flux:select wire:model="stepRole" label="Authorized User Role *" required>
                    @foreach($availableRoles as $r)
                        <flux:select.option value="{{ $r }}">{{ ucfirst(str_replace('_', ' ', $r)) }} ({{ $r }})</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model="stepActionStatus"
                    label="Achieved Audit Status on Approval *"
                    placeholder="e.g. head_approved or certified"
                    description="The audit's status attribute will be set to this upon endorsement."
                    required
                />

                <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-850/40 space-y-3">
                    <flux:checkbox
                        wire:model="stepCanReturn"
                        label="Allow returning audit for revisions"
                        description="If unchecked, reviewer/approver can only endorse and cannot return."
                    />

                    <flux:checkbox
                        wire:model="stepIsFinal"
                        label="This is the final certification step"
                        description="Endorsing this tier officially completes the audit and issues the QR-certified audit certificate."
                    />
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <flux:button wire:click="$set('showModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save Step</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
