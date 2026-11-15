<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

new #[Title('Manage Roles & Permissions')] class extends Component {
    public bool $showRoleModal = false;
    public ?int $editingRoleId = null;
    public string $roleName = '';
    public array $selectedPermissions = [];

    // Grouping dictionary for display
    protected array $permissionGroups = [
        'Process Procedures' => [
            'processes.view',
            'processes.create',
            'processes.edit',
            'processes.delete',
        ],
        'Audit Engagements' => [
            'audits.view',
            'audits.schedule',
            'audits.update',
            'audits.delete',
            'audits.conduct',
        ],
        'Review & Approvals' => [
            'audits.review',
            'approvals.endorse',
            'approvals.final',
        ],
        'Findings & CAPs' => [
            'ncrs.view',
            'ncrs.raise',
            'ncrs.edit',
            'ncrs.delete',
            'caps.submit',
            'caps.review',
        ],
        'Compliance Library' => [
            'documents.view',
            'documents.upload',
            'documents.download',
            'documents.delete',
        ],
        'System Governance' => [
            'settings.manage',
            'workflow.manage',
            'users.manage',
            'roles.manage',
        ],
    ];

    public function mount(): void
    {
        if (! auth()->user()?->can('roles.manage') && ! auth()->user()?->can('settings.manage') && ! auth()->user()?->hasRole('system_admin')) {
            abort(403, 'Unauthorized.');
        }
    }

    public function openCreateModal(): void
    {
        $this->reset(['editingRoleId', 'roleName', 'selectedPermissions']);
        $this->showRoleModal = true;
    }

    public function openEditModal(int $id): void
    {
        $role = Role::with('permissions')->findOrFail($id);
        $this->editingRoleId = $role->id;
        $this->roleName = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        $this->showRoleModal = true;
    }

    public function saveRole(): void
    {
        $this->validate([
            'roleName' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_]+$/'],
            'selectedPermissions' => ['array'],
        ], [
            'roleName.regex' => 'Role name must only contain alphanumeric characters and underscores (e.g. quality_inspector).',
        ]);

        $normalizedName = strtolower(trim($this->roleName));

        if ($this->editingRoleId) {
            $role = Role::findOrFail($this->editingRoleId);
            $role->update(['name' => $normalizedName]);
            $role->syncPermissions($this->selectedPermissions);
            session()->flash('message', "Role '{$role->name}' updated successfully with " . count($this->selectedPermissions) . ' permissions.');
        } else {
            $role = Role::firstOrCreate(['name' => $normalizedName, 'guard_name' => 'web']);
            $role->syncPermissions($this->selectedPermissions);
            session()->flash('message', "New role '{$role->name}' created successfully with " . count($this->selectedPermissions) . ' permissions.');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->showRoleModal = false;
        $this->reset(['editingRoleId', 'roleName', 'selectedPermissions']);
    }

    public function selectAllGroup(string $group): void
    {
        $perms = $this->permissionGroups[$group] ?? [];
        $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $perms)));
    }

    public function deselectAllGroup(string $group): void
    {
        $perms = $this->permissionGroups[$group] ?? [];
        $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, $perms));
    }

    public function deleteRole(int $id): void
    {
        $role = Role::findOrFail($id);

        if ($role->name === 'system_admin') {
            session()->flash('error', 'Cannot delete the system_admin superuser role.');
            return;
        }

        $name = $role->name;
        $role->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        session()->flash('message', "Role '{$name}' deleted.");
    }

    public function with(): array
    {
        return [
            'roles' => Role::withCount('users', 'permissions')->get(),
            'allPermissions' => Permission::orderBy('name')->get(),
            'permissionGroups' => $this->permissionGroups,
        ];
    }
}; ?>

<x-pages::admin.layout :heading="__('NCAA Roles & Permissions')" :subheading="__('Configure RBAC security roles, fine-grained spatie permissions, and multi-role assignments')">
    <div class="space-y-6">

        @if (session()->has('message'))
            <div class="p-3.5 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs sm:text-sm rounded-xl flex items-center justify-between">
                <span>{{ session('message') }}</span>
                <flux:button wire:click="$refresh" size="xs" variant="ghost">Dismiss</flux:button>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="p-3.5 bg-rose-50 dark:bg-rose-950/50 border border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-xs sm:text-sm rounded-xl flex items-center justify-between">
                <span>{{ session('error') }}</span>
                <flux:button wire:click="$refresh" size="xs" variant="ghost">Dismiss</flux:button>
            </div>
        @endif

        <div class="flex items-center justify-between">
            <div class="text-xs text-zinc-500">
                Granular permissions govern sidebar visibility, route access, policies, and Livewire actions.
            </div>
            <flux:button wire:click="openCreateModal" variant="primary" size="sm" icon="plus">
                Create New Role
            </flux:button>
        </div>

        <!-- Roles Table -->
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700 text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-900/50 text-xs font-semibold uppercase text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Role Name</th>
                            <th class="px-4 py-3 text-center">Assigned Users</th>
                            <th class="px-4 py-3 text-center">Permissions Count</th>
                            <th class="px-4 py-3">Key Capabilities</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-800">
                        @forelse($roles as $r)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-750/50 transition">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-zinc-900 dark:text-zinc-100 flex items-center space-x-2">
                                        <flux:badge size="sm" :color="$r->name === 'system_admin' ? 'emerald' : ($r->name === 'lead_auditor' ? 'amber' : 'zinc')">
                                            {{ ucwords(str_replace('_', ' ', $r->name)) }}
                                        </flux:badge>
                                        <span class="font-mono text-xs text-zinc-400">({{ $r->name }})</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center font-semibold text-zinc-700 dark:text-zinc-300">
                                    {{ $r->users_count }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <flux:badge size="xs" color="indigo">{{ $r->permissions_count }} perms</flux:badge>
                                </td>
                                <td class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-400 max-w-md">
                                    @if($r->name === 'system_admin')
                                        <span class="text-emerald-700 dark:text-emerald-300 font-medium">Full Administrative Control (All capabilities enabled)</span>
                                    @else
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($r->permissions->take(5) as $perm)
                                                <span class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 font-mono text-[10px]">
                                                    {{ $perm->name }}
                                                </span>
                                            @endforeach
                                            @if($r->permissions->count() > 5)
                                                <span class="text-zinc-400 text-[10px]">+{{ $r->permissions->count() - 5 }} more</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <flux:button wire:click="openEditModal({{ $r->id }})" size="xs" variant="ghost">
                                        Edit Permissions
                                    </flux:button>
                                    @if($r->name !== 'system_admin')
                                        <flux:button wire:click="deleteRole({{ $r->id }})" wire:confirm="Are you sure you want to delete this role?" size="xs" variant="danger">
                                            Delete
                                        </flux:button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-zinc-500">
                                    No roles found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Role Form Modal -->
        <flux:modal wire:model="showRoleModal" class="md:w-[720px] space-y-4 max-h-[90vh] overflow-y-auto">
            <div>
                <flux:heading size="lg">{{ $editingRoleId ? __('Edit Role & Permissions') : __('Create New Role') }}</flux:heading>
                <flux:subheading>{{ __('Specify role identifier and tick granular permissions for this role') }}</flux:subheading>
            </div>

            <form wire:submit="saveRole" class="space-y-5">
                <flux:input
                    wire:model="roleName"
                    label="Role Identifier (alphanumeric_snake_case) *"
                    placeholder="e.g. quality_inspector"
                    required
                    :disabled="$roleName === 'system_admin' && $editingRoleId !== null"
                />

                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 pb-2">
                        <flux:heading size="sm">Granted Permissions ({{ count($selectedPermissions) }})</flux:heading>
                    </div>

                    @foreach($permissionGroups as $groupTitle => $groupPerms)
                        <div class="p-3.5 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl border border-zinc-200 dark:border-zinc-700 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-xs text-zinc-900 dark:text-zinc-100 uppercase tracking-wider">
                                    {{ $groupTitle }}
                                </span>
                                <div class="flex items-center space-x-2 text-[11px]">
                                    <button type="button" wire:click="selectAllGroup('{{ $groupTitle }}')" class="text-emerald-600 hover:underline">Select all</button>
                                    <span>&bull;</span>
                                    <button type="button" wire:click="deselectAllGroup('{{ $groupTitle }}')" class="text-zinc-400 hover:underline">Clear</button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                                @foreach($groupPerms as $permKey)
                                    <label class="flex items-start space-x-2 text-xs text-zinc-700 dark:text-zinc-300 cursor-pointer p-1.5 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800 transition">
                                        <input
                                            type="checkbox"
                                            wire:model="selectedPermissions"
                                            value="{{ $permKey }}"
                                            class="mt-0.5 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500"
                                        />
                                        <div>
                                            <span class="font-mono text-[11px] font-semibold text-zinc-900 dark:text-zinc-100 block">{{ $permKey }}</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button wire:click="$set('showRoleModal', false)" variant="ghost">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">{{ $editingRoleId ? 'Save Changes' : 'Create Role' }}</flux:button>
                </div>
            </form>
        </flux:modal>

    </div>
</x-pages::admin.layout>
