<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    @can('audits.conduct')
                        <flux:sidebar.item icon="clipboard-document-check" :href="route('field.index')" :current="request()->routeIs('field.*')" wire:navigate>
                            {{ __('Field Audits') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('audits.review')
                        <flux:sidebar.item icon="magnifying-glass-circle" :href="route('review.index')" :current="request()->routeIs('review.*')" wire:navigate>
                            {{ __('Review Queue') }}
                        </flux:sidebar.item>
                    @endcan
                    @canany(['approvals.endorse', 'approvals.final'])
                        <flux:sidebar.item icon="check-badge" :href="route('approvals.index')" :current="request()->routeIs('approvals.*')" wire:navigate>
                            {{ __('Approvals') }}
                        </flux:sidebar.item>
                    @endcanany
                    @can('ncrs.view')
                        <flux:sidebar.item icon="exclamation-triangle" :href="route('ncrs.index')" :current="request()->routeIs('ncrs.*')" wire:navigate>
                            {{ __('NCRs & CAPs') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>

                @canany(['audits.view', 'processes.view', 'documents.view'])
                    <flux:sidebar.group :heading="__('Audit Operations')" class="grid">
                        @can('audits.view')
                            <flux:sidebar.item icon="clipboard-document-list" :href="route('audits.index')" :current="request()->routeIs('audits.*')" wire:navigate>
                                {{ __('Audits') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('processes.view')
                            <flux:sidebar.item icon="arrow-path-rounded-square" :href="route('processes.index')" :current="request()->routeIs('processes.*')" wire:navigate>
                                {{ __('Processes') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('documents.view')
                            <flux:sidebar.item icon="document-text" :href="route('admin.documents')" :current="request()->routeIs('admin.documents')" wire:navigate>
                                {{ __('Document Library') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany

                @canany(['settings.manage', 'users.manage', 'roles.manage'])
                    <flux:sidebar.group :heading="__('Administration')" class="grid">
                        <flux:sidebar.item icon="building-office-2" :href="route('admin.directorates')" :current="request()->routeIs('admin.directorates')" wire:navigate>
                            {{ __('Directorates') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="building-office" :href="route('admin.departments')" :current="request()->routeIs('admin.departments')" wire:navigate>
                            {{ __('Departments') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="squares-2x2" :href="route('admin.units')" :current="request()->routeIs('admin.units')" wire:navigate>
                            {{ __('Units') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="list-bullet" :href="route('admin.lookups')" :current="request()->routeIs('admin.lookups')" wire:navigate>
                            {{ __('Lookup Lists') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate>
                            {{ __('User Accounts') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="shield-check" :href="route('admin.roles')" :current="request()->routeIs('admin.roles')" wire:navigate>
                            {{ __('Roles & Permissions') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.settings')" :current="request()->routeIs('admin.settings')" wire:navigate>
                            {{ __('System Settings') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="arrows-right-left" :href="route('admin.workflow')" :current="request()->routeIs('admin.workflow')" wire:navigate>
                            {{ __('Approval Workflow') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endcanany
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <flux:header class="w-full border-b border-zinc-200 dark:border-zinc-750 px-4 py-2 flex items-center justify-between bg-white dark:bg-zinc-800">
            <div class="flex items-center space-x-2">
                <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
                <span class="text-xs font-bold text-zinc-500 uppercase tracking-wider hidden sm:inline">NCAA QA/IA Intelligence</span>
            </div>

            <div class="flex items-center space-x-2">
                <livewire:notification-bell />

                <div class="lg:hidden">
                    <flux:dropdown position="top" align="end">
                        <flux:profile
                            :initials="auth()->user()->initials()"
                            icon-trailing="chevron-down"
                        />

                        <flux:menu>
                            <div class="p-0 text-sm font-normal">
                                <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                    <flux:avatar
                                        :name="auth()->user()->name"
                                        :initials="auth()->user()->initials()"
                                    />

                                    <div class="grid flex-1 text-start text-sm leading-tight">
                                        <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                        <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                    </div>
                                </div>
                            </div>

                            <flux:menu.separator />

                            <flux:menu.radio.group>
                                <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                                    {{ __('Settings') }}
                                </flux:menu.item>
                            </flux:menu.radio.group>

                            <flux:menu.separator />

                            <form method="POST" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <flux:menu.item
                                    as="button"
                                    type="submit"
                                    icon="arrow-right-start-on-rectangle"
                                    class="w-full cursor-pointer"
                                    data-test="logout-button"
                                >
                                    {{ __('Log out') }}
                                </flux:menu.item>
                            </form>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </div>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
