<?php

use App\Models\Directorate;
use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('NCAA Directorates Admin')] class extends Component {
    public $name = '';
    public $code = '';
    public $head_of_directorate = '';
    public $email = '';
    public $description = '';
    public $editingId = null;
    public $showModal = false;

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:directorates,code,' . $this->editingId,
            'head_of_directorate' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'description' => 'nullable|string',
        ];
    }

    public function create()
    {
        $this->reset(['name', 'code', 'head_of_directorate', 'email', 'description', 'editingId']);
        $this->showModal = true;
    }

    public function edit($id)
    {
        $dir = Directorate::findOrFail($id);
        $this->editingId = $dir->id;
        $this->name = $dir->name;
        $this->code = $dir->code;
        $this->head_of_directorate = $dir->head_of_directorate;
        $this->email = $dir->email;
        $this->description = $dir->description;
        $this->showModal = true;
    }

    public function save()
    {
        $validated = $this->validate();

        Directorate::updateOrCreate(
            ['id' => $this->editingId],
            $validated
        );

        $this->showModal = false;
        $this->reset(['name', 'code', 'head_of_directorate', 'email', 'description', 'editingId']);
        session()->flash('message', 'Directorate saved successfully.');
    }

    public function delete($id)
    {
        Directorate::findOrFail($id)->delete();
        session()->flash('message', 'Directorate deleted.');
    }

    public function with()
    {
        return [
            'directorates' => Directorate::withCount(['departments', 'users'])->latest()->get(),
        ];
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('NCAA Directorates')" :subheading="__('Manage authority directorates (e.g. DSD, DAWS, DOLG, DATR)')">

        @if (session()->has('message'))
            <div class="mb-4 p-3 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-sm rounded-xl">
                {{ session('message') }}
            </div>
        @endif

        <div class="flex justify-between items-center mb-6">
            <flux:text class="text-sm text-zinc-500">Total registered directorates: {{ count($directorates) }}</flux:text>
            <flux:button wire:click="create" variant="primary" icon="plus">
                {{ __('Add Directorate') }}
            </flux:button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700 text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 text-xs font-semibold uppercase text-zinc-500">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Director</th>
                        <th class="px-4 py-3 text-center">Departments</th>
                        <th class="px-4 py-3 text-center">Staff</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-800">
                    @forelse($directorates as $dir)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            <td class="px-4 py-3 font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                {{ $dir->code }}
                            </td>
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $dir->name }}
                            </td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                {{ $dir->head_of_directorate ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-center font-semibold text-zinc-700 dark:text-zinc-300">
                                {{ $dir->departments_count }}
                            </td>
                            <td class="px-4 py-3 text-center font-semibold text-zinc-700 dark:text-zinc-300">
                                {{ $dir->users_count }}
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <flux:button wire:click="edit({{ $dir->id }})" size="sm" variant="ghost">Edit</flux:button>
                                <flux:button wire:click="delete({{ $dir->id }})" wire:confirm="Are you sure you want to delete this directorate?" size="sm" variant="danger">Delete</flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500">
                                No directorates recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <flux:modal wire:model="showModal" class="md:w-96 space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit Directorate' : 'Add Directorate' }}</flux:heading>
                <flux:subheading>Enter NCAA directorate details</flux:subheading>
            </div>

            <form wire:submit="save" class="space-y-4">
                <flux:input wire:model="name" label="Directorate Name" placeholder="e.g. Directorate of Special Duties" required />
                <flux:input wire:model="code" label="Directorate Code" placeholder="e.g. DSD" required />
                <flux:input wire:model="head_of_directorate" label="Director / Head of Directorate" placeholder="e.g. Capt. Musa Bello" />
                <flux:input wire:model="email" type="email" label="Official Email" placeholder="e.g. dsd@ncaa.gov.ng" />
                <flux:textarea wire:model="description" label="Mandate / Description" rows="2" />

                <div class="flex justify-end space-x-2 pt-2">
                    <flux:button wire:click="$set('showModal', false)" variant="ghost">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Save Directorate</flux:button>
                </div>
            </form>
        </flux:modal>

    </x-pages::admin.layout>
</section>
