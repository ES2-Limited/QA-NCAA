<?php

use App\Models\Directorate;
use App\Models\Process;
use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Processes & Checklists')] class extends Component {
    public $search = '';
    public $filterType = '';
    public $filterDirectorate = '';

    public function deleteProcess($id)
    {
        $process = Process::findOrFail($id);
        $process->questions()->delete();
        $process->documents()->detach();
        $process->delete();

        session()->flash('message', 'Process template deleted successfully.');
    }

    public function with()
    {
        $query = Process::with(['directorate', 'department', 'unit', 'questions'])
            ->withCount('questions')
            ->latest();

        if (!empty($this->search)) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        if (!empty($this->filterType)) {
            $query->where('type', $this->filterType);
        }

        if (!empty($this->filterDirectorate)) {
            $query->where('directorate_id', $this->filterDirectorate);
        }

        return [
            'processes' => $query->get(),
            'directorates' => Directorate::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header banner -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Processes & Audit Checklists') }}</flux:heading>
            <flux:subheading>{{ __('Manage recurring quality procedures, surveillance templates, and standard audit questionnaires') }}</flux:subheading>
        </div>
        <flux:button href="{{ route('processes.create') }}" variant="primary" icon="plus" wire:navigate>
            {{ __('New Process') }}
        </flux:button>
    </div>

    @if (session()->has('message'))
        <div class="p-3 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-sm rounded-xl">
            {{ session('message') }}
        </div>
    @endif

    <!-- Search & Filters -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl border border-zinc-200 dark:border-zinc-700">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search processes by name..." icon="magnifying-glass" />

        <flux:select wire:model.live="filterType" placeholder="All Types">
            <flux:select.option value="">All Types</flux:select.option>
            <flux:select.option value="routine">Routine</flux:select.option>
            <flux:select.option value="unplanned">Unplanned / Spot Check</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="filterDirectorate" placeholder="All Directorates">
            <flux:select.option value="">All Directorates</flux:select.option>
            @foreach($directorates as $dir)
                <flux:select.option value="{{ $dir->id }}">{{ $dir->code }} - {{ $dir->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700 text-left text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-900/50 text-xs font-semibold uppercase text-zinc-500">
                <tr>
                    <th class="px-4 py-3">Process Name</th>
                    <th class="px-4 py-3">Hierarchy (Dir/Dept/Unit)</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Frequency</th>
                    <th class="px-4 py-3">Next Due</th>
                    <th class="px-4 py-3 text-center">Questions</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-800">
                @forelse($processes as $proc)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                        <td class="px-4 py-3">
                            <a href="{{ route('processes.show', $proc->id) }}" class="font-bold text-zinc-900 dark:text-zinc-100 hover:text-emerald-600 dark:hover:text-emerald-400" wire:navigate>
                                {{ $proc->name }}
                            </a>
                            @if($proc->description)
                                <div class="text-xs text-zinc-500 truncate max-w-xs mt-0.5">{{ $proc->description }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-400">
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $proc->directorate->code ?? 'NCAA-Wide' }}</div>
                            <div class="text-zinc-500">{{ $proc->department->name ?? ($proc->unit->name ?? '—') }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <flux:badge size="sm" :color="$proc->type === 'routine' ? 'blue' : 'amber'">
                                {{ ucfirst($proc->type) }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 text-xs text-zinc-700 dark:text-zinc-300">
                            {{ $proc->frequency ? ucfirst($proc->frequency) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-400 font-mono">
                            {{ $proc->next_due_at ? $proc->next_due_at->format('M d, Y') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-center font-bold text-zinc-800 dark:text-zinc-200">
                            <flux:badge size="sm" color="zinc">{{ $proc->questions_count }}</flux:badge>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <flux:badge size="sm" :color="$proc->status === 'active' ? 'emerald' : 'zinc'">
                                {{ ucfirst($proc->status) }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 text-right space-x-1 whitespace-nowrap">
                            <flux:button href="{{ route('processes.show', $proc->id) }}" size="sm" variant="ghost" wire:navigate>
                                View
                            </flux:button>
                            <flux:button href="{{ route('processes.edit', $proc->id) }}" size="sm" variant="ghost" wire:navigate>
                                Edit
                            </flux:button>
                            <flux:button wire:click="deleteProcess({{ $proc->id }})" wire:confirm="Are you sure you want to delete this process template and its checklist questions?" size="sm" variant="danger">
                                Delete
                            </flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-zinc-500">
                            No process templates found. Click "New Process" to create one.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
