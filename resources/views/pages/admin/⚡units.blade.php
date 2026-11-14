<?php

use App\Models\Department;
use App\Models\Unit;
use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('NCAA Units Admin')] class extends Component {
    public $department_id = '';
    public $name = '';
    public $code = '';
    public $head_of_unit = '';
    public $description = '';
    public $editingId = null;
    public $showModal = false;

    public function rules()
    {
        return [
            'department_id' => 'required|exists:departments,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:units,code,' . $this->editingId,
            'head_of_unit' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ];
    }

    public function create()
    {
        $this->reset(['department_id', 'name', 'code', 'head_of_unit', 'description', 'editingId']);
        $this->showModal = true;
    }

    public function edit($id)
    {
        $unit = Unit::findOrFail($id);
        $this->editingId = $unit->id;
        $this->department_id = $unit->department_id;
        $this->name = $unit->name;
        $this->code = $unit->code;
        $this->head_of_unit = $unit->head_of_unit;
        $this->description = $unit->description;
        $this->showModal = true;
    }

    public function save()
    {
        $validated = $this->validate();

        Unit::updateOrCreate(
            ['id' => $this->editingId],
            $validated
        );

        $this->showModal = false;
        $this->reset(['department_id', 'name', 'code', 'head_of_unit', 'description', 'editingId']);
        session()->flash('message', 'Unit saved successfully.');
    }

    public function delete($id)
    {
        Unit::findOrFail($id)->delete();
        session()->flash('message', 'Unit deleted.');
    }

    public function with()
    {
        return [
            'units' => Unit::with('department.directorate')->withCount('users')->latest()->get(),
            'departments' => Department::with('directorate')->orderBy('name')->get(),
        ];
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('NCAA Operational Units')" :subheading="__('Manage operational and audit units structured under departments')">

        @if (session()->has('message'))
            <div class="mb-4 p-3 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-sm rounded-xl">
                {{ session('message') }}
            </div>
        @endif

        <div class="flex justify-between items-center mb-6">
            <flux:text class="text-sm text-zinc-500">Total registered units: {{ count($units) }}</flux:text>
            <flux:button wire:click="create" variant="primary" icon="plus">
                {{ __('Add Unit') }}
            </flux:button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700 text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 text-xs font-semibold uppercase text-zinc-500">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Unit Name</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3">Directorate</th>
                        <th class="px-4 py-3">Head of Unit</th>
                        <th class="px-4 py-3 text-center">Staff</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-800">
                    @forelse($units as $unit)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            <td class="px-4 py-3 font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                {{ $unit->code }}
                            </td>
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $unit->name }}
                            </td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                {{ $unit->department->name ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" color="zinc">{{ $unit->department->directorate->code ?? 'N/A' }}</flux:badge>
                            </td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                {{ $unit->head_of_unit ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-center font-semibold text-zinc-700 dark:text-zinc-300">
                                {{ $unit->users_count }}
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <flux:button wire:click="edit({{ $unit->id }})" size="sm" variant="ghost">Edit</flux:button>
                                <flux:button wire:click="delete({{ $unit->id }})" wire:confirm="Are you sure you want to delete this unit?" size="sm" variant="danger">Delete</flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-zinc-500">
                                No units recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <flux:modal wire:model="showModal" class="md:w-96 space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit Unit' : 'Add Unit' }}</flux:heading>
                <flux:subheading>Assign unit under an NCAA department</flux:subheading>
            </div>

            <form wire:submit="save" class="space-y-4">
                <flux:select wire:model="department_id" label="Parent Department" placeholder="Select Department" required>
                    @foreach($departments as $dept)
                        <flux:select.option value="{{ $dept->id }}">{{ $dept->directorate->code ?? '' }} - {{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="name" label="Unit Name" placeholder="e.g. Flight Safety Inspection Unit" required />
                <flux:input wire:model="code" label="Unit Code" placeholder="e.g. FSI-U" required />
                <flux:input wire:model="head_of_unit" label="Head of Unit" placeholder="e.g. Capt. S. Danbaba" />
                <flux:textarea wire:model="description" label="Unit Mandate" rows="2" />

                <div class="flex justify-end space-x-2 pt-2">
                    <flux:button wire:click="$set('showModal', false)" variant="ghost">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Save Unit</flux:button>
                </div>
            </form>
        </flux:modal>

    </x-pages::admin.layout>
</section>
