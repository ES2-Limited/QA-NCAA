<?php

use App\Models\Lookup;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Manage Lookup Lists')] class extends Component {
    public string $activeTab = 'process_category';

    // Modal state for Add / Edit
    public bool $showModal = false;
    public ?int $editingId = null;

    // Form fields
    public string $formType = 'process_category';
    public string $formKey = '';
    public string $formName = '';
    public string $formColour = 'zinc';
    public int $formSortOrder = 0;
    public bool $formIsActive = true;

    protected array $colourOptions = [
        'zinc' => 'Zinc / Neutral',
        'emerald' => 'Emerald / Green',
        'blue' => 'Blue',
        'indigo' => 'Indigo',
        'cyan' => 'Cyan',
        'amber' => 'Amber / Orange',
        'rose' => 'Rose / Red',
        'purple' => 'Purple',
    ];

    public function mount(): void
    {
        if (! auth()->user()?->can('settings.manage')) {
            abort(403, 'Unauthorized.');
        }

        $this->formType = $this->activeTab;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->formType = $tab;
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->formType = $this->activeTab;
        $maxOrder = Lookup::where('type', $this->activeTab)->max('sort_order') ?? 0;
        $this->formSortOrder = $maxOrder + 1;
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $lookup = Lookup::findOrFail($id);
        $this->editingId = $lookup->id;
        $this->formType = $lookup->type;
        $this->formKey = $lookup->key ?? '';
        $this->formName = $lookup->name;
        $this->formColour = $lookup->colour ?: 'zinc';
        $this->formSortOrder = (int) $lookup->sort_order;
        $this->formIsActive = (bool) $lookup->is_active;

        $this->showModal = true;
    }

    public function saveLookup(): void
    {
        $this->validate([
            'formName' => ['required', 'string', 'max:255'],
            'formColour' => ['required', 'string', 'max:50'],
            'formSortOrder' => ['required', 'integer', 'min:0'],
        ]);

        $key = ! empty($this->formKey)
            ? strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $this->formKey), '_'))
            : strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $this->formName), '_'));

        $data = [
            'type' => $this->formType,
            'key' => $key,
            'name' => trim($this->formName),
            'colour' => $this->formColour,
            'sort_order' => $this->formSortOrder,
            'is_active' => $this->formIsActive,
        ];

        if ($this->editingId) {
            $lookup = Lookup::findOrFail($this->editingId);
            $lookup->update($data);
            session()->flash('message', "Lookup '{$lookup->name}' updated successfully.");
        } else {
            Lookup::create($data);
            session()->flash('message', "New lookup item '{$data['name']}' created successfully.");
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $lookup = Lookup::findOrFail($id);
        $lookup->update(['is_active' => ! $lookup->is_active]);
        session()->flash('message', "Lookup '{$lookup->name}' status updated.");
    }

    public function moveOrder(int $id, string $direction): void
    {
        $lookup = Lookup::findOrFail($id);
        $siblings = Lookup::where('type', $lookup->type)->orderBy('sort_order')->orderBy('id')->get();

        $index = $siblings->search(fn ($item) => $item->id === $lookup->id);

        if ($direction === 'up' && $index > 0) {
            $prev = $siblings[$index - 1];
            $tempOrder = $lookup->sort_order;
            $lookup->update(['sort_order' => $prev->sort_order]);
            $prev->update(['sort_order' => $tempOrder]);
        } elseif ($direction === 'down' && $index < count($siblings) - 1) {
            $next = $siblings[$index + 1];
            $tempOrder = $lookup->sort_order;
            $lookup->update(['sort_order' => $next->sort_order]);
            $next->update(['sort_order' => $tempOrder]);
        }
    }

    public function deleteLookup(int $id): void
    {
        $lookup = Lookup::findOrFail($id);
        $name = $lookup->name;
        $lookup->delete();

        session()->flash('message', "Lookup '{$name}' deleted.");
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->formKey = '';
        $this->formName = '';
        $this->formColour = 'zinc';
        $this->formSortOrder = 0;
        $this->formIsActive = true;
    }

    public function with(): array
    {
        $items = Lookup::where('type', $this->activeTab)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return [
            'items' => $items,
            'colourOptions' => $this->colourOptions,
        ];
    }
}; ?>

<x-pages::admin.layout :heading="__('NCAA Lookup Lists')" :subheading="__('Admin-managed operational taxonomies, categories, document classifications, and status badges')">
    <div class="space-y-6">

        @if (session()->has('message'))
            <div class="p-3.5 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs sm:text-sm rounded-xl flex items-center justify-between">
                <span>{{ session('message') }}</span>
                <flux:button wire:click="$refresh" size="xs" variant="ghost">Dismiss</flux:button>
            </div>
        @endif

        <!-- Tab Selector -->
        <div class="flex items-center justify-between flex-wrap gap-4 border-b border-zinc-200 dark:border-zinc-700 pb-3">
            <div class="flex items-center space-x-2">
                <button
                    wire:click="setTab('process_category')"
                    class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition {{ $activeTab === 'process_category' ? 'bg-emerald-700 text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                    Process Categories
                </button>
                <button
                    wire:click="setTab('document_type')"
                    class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition {{ $activeTab === 'document_type' ? 'bg-emerald-700 text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                    Document Types
                </button>
                <button
                    wire:click="setTab('audit_status')"
                    class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition {{ $activeTab === 'audit_status' ? 'bg-emerald-700 text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                    Audit Statuses
                </button>
                <button
                    wire:click="setTab('ncr_status')"
                    class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition {{ $activeTab === 'ncr_status' ? 'bg-emerald-700 text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                    NCR Statuses
                </button>
            </div>

            <flux:button wire:click="openCreateModal" variant="primary" size="sm" icon="plus">
                Add {{ match($activeTab) {
                    'process_category' => 'Category',
                    'document_type' => 'Document Type',
                    'audit_status' => 'Audit Status',
                    'ncr_status' => 'NCR Status',
                    default => 'Lookup Item'
                } }}
            </flux:button>
        </div>

        <!-- Lookup items list -->
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700 text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-900/50 text-xs font-semibold uppercase text-zinc-500">
                        <tr>
                            <th class="px-4 py-3 text-center w-16">Order</th>
                            <th class="px-4 py-3">Name / Label</th>
                            <th class="px-4 py-3">Identifier (Key)</th>
                            <th class="px-4 py-3">Badge Preview & Colour</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-800">
                        @forelse($items as $index => $item)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center space-x-1">
                                        <flux:button wire:click="moveOrder({{ $item->id }}, 'up')" size="xs" variant="ghost" icon="chevron-up" :disabled="$loop->first" />
                                        <span class="font-mono text-xs font-semibold text-zinc-600 dark:text-zinc-400">{{ $item->sort_order }}</span>
                                        <flux:button wire:click="moveOrder({{ $item->id }}, 'down')" size="xs" variant="ghost" icon="chevron-down" :disabled="$loop->last" />
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">
                                        {{ $item->name }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-zinc-500">
                                    {{ $item->key }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center space-x-2">
                                        <flux:badge size="sm" :color="$item->colour">
                                            {{ $item->name }}
                                        </flux:badge>
                                        <span class="text-xs text-zinc-400">({{ $item->colour }})</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button
                                        type="button"
                                        wire:click="toggleActive({{ $item->id }})"
                                        class="cursor-pointer">
                                        <flux:badge size="xs" :color="$item->is_active ? 'emerald' : 'zinc'">
                                            {{ $item->is_active ? 'Active' : 'Disabled' }}
                                        </flux:badge>
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <flux:button wire:click="openEditModal({{ $item->id }})" size="xs" variant="ghost">
                                        Edit
                                    </flux:button>
                                    <flux:button wire:click="deleteLookup({{ $item->id }})" wire:confirm="Are you sure you want to remove this lookup entry?" size="xs" variant="danger">
                                        Delete
                                    </flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-zinc-500 text-xs">
                                    No lookup entries configured for this category. Click &ldquo;Add&rdquo; above to create one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add / Edit Modal -->
        <flux:modal wire:model="showModal" class="md:w-[480px] space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit Lookup Item') : __('Add Lookup Item') }}</flux:heading>
                <flux:subheading>{{ __('Configure display name, identifier key, badge colour and sort ordering') }}</flux:subheading>
            </div>

            <form wire:submit="saveLookup" class="space-y-4">
                <flux:input wire:model="formName" label="Display Name *" placeholder="e.g. Surveillance Audit or Closed" required />

                <flux:input wire:model="formKey" label="System Key (optional, auto-derived)" placeholder="e.g. surveillance or closed" />

                <flux:select wire:model="formColour" label="Badge Colour *">
                    @foreach($colourOptions as $key => $label)
                        <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="formSortOrder" type="number" label="Sort Order *" min="0" required />

                    <div class="flex items-center pt-6 space-x-2">
                        <flux:checkbox wire:model="formIsActive" label="Active Flag" />
                    </div>
                </div>

                <div class="p-3 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl border border-zinc-200 dark:border-zinc-700 space-y-1.5">
                    <div class="text-[11px] font-semibold uppercase text-zinc-400">Live Preview</div>
                    <div class="flex items-center space-x-2">
                        <flux:badge size="sm" :color="$formColour">
                            {{ $formName ?: 'Preview Badge' }}
                        </flux:badge>
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <flux:button wire:click="$set('showModal', false)" variant="ghost">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">{{ $editingId ? 'Save Changes' : 'Create Item' }}</flux:button>
                </div>
            </form>
        </flux:modal>

    </div>
</x-pages::admin.layout>
