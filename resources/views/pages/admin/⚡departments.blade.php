<?php

use App\Models\Department;
use App\Models\Directorate;
use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('NCAA Departments Admin')] class extends Component {
    public $directorate_id = '';
    public $name = '';
    public $code = '';
    public $head_of_department = '';
    public $description = '';
    public $editingId = null;
    public $showModal = false;

    public function rules()
    {
        return [
            'directorate_id' => 'required|exists:directorates,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:departments,code,' . $this->editingId,
            'head_of_department' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ];
    }

    public function create()
    {
        $this->reset(['directorate_id', 'name', 'code', 'head_of_department', 'description', 'editingId']);
        $this->showModal = true;
    }

    public function edit($id)
    {
        $dept = Department::findOrFail($id);
        $this->editingId = $dept->id;
        $this->directorate_id = $dept->directorate_id;
        $this->name = $dept->name;
        $this->code = $dept->code;
        $this->head_of_department = $dept->head_of_department;
        $this->description = $dept->description;
        $this->showModal = true;
    }

    public function save()
    {
        $validated = $this->validate();

        Department::updateOrCreate(
            ['id' => $this->editingId],
            $validated
        );

        $this->showModal = false;
        $this->reset(['directorate_id', 'name', 'code', 'head_of_department', 'description', 'editingId']);
        session()->flash('message', 'Department saved successfully.');
    }

    public function delete($id)
    {
        Department::findOrFail($id)->delete();
        session()->flash('message', 'Department deleted.');
    }

    public function with()
    {
        return [
            'departments' => Department::with('directorate')->withCount(['units', 'users'])->latest()->get(),
            'directorates' => Directorate::orderBy('name')->get(),
        ];
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('NCAA Departments')" :subheading="__('Manage departments structured under directorates')">

        @if (session()->has('message'))
            <div class="mb-4 p-3 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-sm rounded-xl">
                {{ session('message') }}
            </div>
        @endif

        <div class="flex justify-between items-center mb-6">
            <flux:text class="text-sm text-zinc-500">Total registered departments: {{ count($departments) }}</flux:text>
            <flux:button wire:click="create" variant="primary" icon="plus">
                {{ __('Add Department') }}
            </flux:button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700 text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 text-xs font-semibold uppercase text-zinc-500">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Department Name</th>
                        <th class="px-4 py-3">Directorate</th>
                        <th class="px-4 py-3">Head of Dept</th>
                        <th class="px-4 py-3 text-center">Units</th>
                        <th class="px-4 py-3 text-center">Staff</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-800">
                    @forelse($departments as $dept)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            <td class="px-4 py-3 font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                {{ $dept->code }}
                            </td>
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $dept->name }}
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" color="zinc">{{ $dept->directorate->code ?? 'N/A' }}</flux:badge>
                            </td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                {{ $dept->head_of_department ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-center font-semibold text-zinc-700 dark:text-zinc-300">
                                {{ $dept->units_count }}
                            </td>
                            <td class="px-4 py-3 text-center font-semibold text-zinc-700 dark:text-zinc-300">
                                {{ $dept->users_count }}
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <flux:button wire:click="edit({{ $dept->id }})" size="sm" variant="ghost">Edit</flux:button>
                                <flux:button wire:click="delete({{ $dept->id }})" wire:confirm="Are you sure you want to delete this department?" size="sm" variant="danger">Delete</flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-zinc-500">
                                No departments recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <flux:modal wire:model="showModal" class="md:w-96 space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit Department' : 'Add Department' }}</flux:heading>
                <flux:subheading>Assign department under an NCAA directorate</flux:subheading>
            </div>

            <form wire:submit="save" class="space-y-4">
                <flux:select wire:model="directorate_id" label="Parent Directorate" placeholder="Select Directorate" required>
                    @foreach($directorates as $dir)
                        <flux:select.option value="{{ $dir->id }}">{{ $dir->code }} - {{ $dir->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="name" label="Department Name" placeholder="e.g. Quality Assurance & Internal Audit" required />
                <flux:input wire:model="code" label="Department Code" placeholder="e.g. DSD-QA" required />
                <flux:input wire:model="head_of_department" label="Head of Department" placeholder="e.g. Engr. Folake Adeleke" />
                <flux:textarea wire:model="description" label="Department Functions" rows="2" />

                <div class="flex justify-end space-x-2 pt-2">
                    <flux:button wire:click="$set('showModal', false)" variant="ghost">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Save Department</flux:button>
                </div>
            </form>
        </flux:modal>

    </x-pages::admin.layout>
</section>
