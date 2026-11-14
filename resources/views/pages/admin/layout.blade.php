<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('Admin Management') }}">
            <flux:navlist.item :href="route('admin.directorates')" :current="request()->routeIs('admin.directorates')" wire:navigate>{{ __('Directorates') }}</flux:navlist.item>
            <flux:navlist.item :href="route('admin.departments')" :current="request()->routeIs('admin.departments')" wire:navigate>{{ __('Departments') }}</flux:navlist.item>
            <flux:navlist.item :href="route('admin.units')" :current="request()->routeIs('admin.units')" wire:navigate>{{ __('Units') }}</flux:navlist.item>
            <flux:navlist.item :href="route('admin.documents')" :current="request()->routeIs('admin.documents')" wire:navigate>{{ __('Document Library') }}</flux:navlist.item>
            <flux:navlist.item :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate>{{ __('User Accounts') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading size="xl">{{ $heading ?? 'Administration' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? 'Manage NCAA organizational structure, directorates, departments, units, and system access' }}</flux:subheading>

        <div class="mt-6 w-full">
            {{ $slot }}
        </div>
    </div>
</div>
