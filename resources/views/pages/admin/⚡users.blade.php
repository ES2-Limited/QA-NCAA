<?php

use App\Models\Department;
use App\Models\Directorate;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\Attributes\Title;
use Spatie\Permission\Models\Role;

new #[Title('NCAA Users Admin')] class extends Component {
    public $name = '';
    public $email = '';
    public $password = '';
    public $staff_id = '';
    public $phone = '';
    public $directorate_id = '';
    public $department_id = '';
    public $unit_id = '';
    public $selectedRole = 'auditor';
    public $editingId = null;
    public $showModal = false;

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $this->editingId,
            'password' => $this->editingId ? 'nullable|min:6' : 'required|min:6',
            'staff_id' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
            'directorate_id' => 'nullable|exists:directorates,id',
            'department_id' => 'nullable|exists:departments,id',
            'unit_id' => 'nullable|exists:units,id',
            'selectedRole' => 'required|exists:roles,name',
        ];
    }

    public function create()
    {
        $this->reset(['name', 'email', 'password', 'staff_id', 'phone', 'directorate_id', 'department_id', 'unit_id', 'selectedRole', 'editingId']);
        $this->selectedRole = 'auditor';
        $this->showModal = true;
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->staff_id = $user->staff_id;
        $this->phone = $user->phone;
        $this->directorate_id = $user->directorate_id;
        $this->department_id = $user->department_id;
        $this->unit_id = $user->unit_id;
        $this->selectedRole = $user->roles->first()?->name ?? 'auditor';
        $this->showModal = true;
    }

    public function save()
    {
        $validated = $this->validate();

        $userData = [
            'name' => $this->name,
            'email' => $this->email,
            'staff_id' => $this->staff_id,
            'phone' => $this->phone,
            'directorate_id' => $this->directorate_id ?: null,
            'department_id' => $this->department_id ?: null,
            'unit_id' => $this->unit_id ?: null,
            'role' => $this->selectedRole,
        ];

        if (!empty($this->password)) {
            $userData['password'] = Hash::make($this->password);
        }

        $user = User::updateOrCreate(
            ['id' => $this->editingId],
            $userData
        );

        $user->syncRoles([$this->selectedRole]);

        $this->showModal = false;
        $this->reset(['name', 'email', 'password', 'staff_id', 'phone', 'directorate_id', 'department_id', 'unit_id', 'selectedRole', 'editingId']);
        session()->flash('message', 'User account saved successfully.');
    }

    public function delete($id)
    {
        if ($id == auth()->id()) {
            session()->flash('error', 'Cannot delete your own active account.');
            return;
        }

        User::findOrFail($id)->delete();
        session()->flash('message', 'User deleted.');
    }

    public function with()
    {
        return [
            'users' => User::with(['directorate', 'dept', 'unit', 'roles'])->latest()->get(),
            'directorates' => Directorate::orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
            'roles' => Role::all(),
        ];
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('NCAA User Accounts & Roles')" :subheading="__('Manage personnel, staff IDs, department assignments, and Spatie RBAC roles')">

        @if (session()->has('message'))
            <div class="mb-4 p-3 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-sm rounded-xl">
                {{ session('message') }}
            </div>
        @endif
        @if (session()->has('error'))
            <div class="mb-4 p-3 bg-rose-50 dark:bg-rose-950/50 border border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-sm rounded-xl">
                {{ session('error') }}
            </div>
        @endif

        <div class="flex justify-between items-center mb-6">
            <flux:text class="text-sm text-zinc-500">Total registered users: {{ count($users) }}</flux:text>
            <flux:button wire:click="create" variant="primary" icon="plus">
                {{ __('Add User') }}
            </flux:button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700 text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 text-xs font-semibold uppercase text-zinc-500">
                    <tr>
                        <th class="px-4 py-3">Staff</th>
                        <th class="px-4 py-3">Staff ID</th>
                        <th class="px-4 py-3">Directorate / Dept</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-800">
                    @forelse($users as $u)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            <td class="px-4 py-3">
                                <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $u->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $u->email }}</div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs font-semibold text-zinc-600 dark:text-zinc-400">
                                {{ $u->staff_id ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-700 dark:text-zinc-300">
                                <div><span class="font-semibold">{{ $u->directorate->code ?? 'N/A' }}</span></div>
                                <div class="text-zinc-500">{{ $u->dept->name ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @forelse($u->roles as $role)
                                    <flux:badge size="sm" :color="$role->name === 'system_admin' ? 'emerald' : ($role->name === 'lead_auditor' ? 'amber' : 'zinc')">
                                        {{ str_replace('_', ' ', $role->name) }}
                                    </flux:badge>
                                @empty
                                    <flux:badge size="sm" color="zinc">user</flux:badge>
                                @endforelse
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <flux:button wire:click="edit({{ $u->id }})" size="sm" variant="ghost">Edit</flux:button>
                                @if($u->id !== auth()->id())
                                    <flux:button wire:click="delete({{ $u->id }})" wire:confirm="Are you sure you want to delete this user?" size="sm" variant="danger">Delete</flux:button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-zinc-500">
                                No users found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <flux:modal wire:model="showModal" class="md:w-[480px] space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit User' : 'Add User' }}</flux:heading>
                <flux:subheading>Personnel credentials and role assignment</flux:subheading>
            </div>

            <form wire:submit="save" class="space-y-4">
                <flux:input wire:model="name" label="Full Name" placeholder="e.g. Capt. Musa Bello" required />
                <flux:input wire:model="email" type="email" label="Email Address" placeholder="e.g. user@ncaa.gov.ng" required />
                <flux:input wire:model="password" type="password" label="Password" placeholder="{{ $editingId ? 'Leave blank to keep current password' : 'At least 6 characters' }}" />

                <div class="grid grid-cols-2 gap-3">
                    <flux:input wire:model="staff_id" label="Staff ID" placeholder="NCAA/DSD/001" />
                    <flux:input wire:model="phone" label="Phone" placeholder="+234..." />
                </div>

                <flux:select wire:model="selectedRole" label="Spatie RBAC Role" required>
                    @foreach($roles as $role)
                        <flux:select.option value="{{ $role->name }}">{{ ucwords(str_replace('_', ' ', $role->name)) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="directorate_id" label="Directorate">
                    <flux:select.option value="">None / Unassigned</flux:select.option>
                    @foreach($directorates as $dir)
                        <flux:select.option value="{{ $dir->id }}">{{ $dir->code }} - {{ $dir->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="department_id" label="Department">
                    <flux:select.option value="">None / Unassigned</flux:select.option>
                    @foreach($departments as $dept)
                        <flux:select.option value="{{ $dept->id }}">{{ $dept->directorate->code ?? '' }} - {{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="unit_id" label="Unit">
                    <flux:select.option value="">None / Unassigned</flux:select.option>
                    @foreach($units as $un)
                        <flux:select.option value="{{ $un->id }}">{{ $un->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex justify-end space-x-2 pt-2">
                    <flux:button wire:click="$set('showModal', false)" variant="ghost">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Save User</flux:button>
                </div>
            </form>
        </flux:modal>

    </x-pages::admin.layout>
</section>
