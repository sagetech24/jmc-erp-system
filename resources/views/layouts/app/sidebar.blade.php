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
                {{-- <flux:sidebar.group :heading="__('Platform')" class="grid"> --}}
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clipboard-document-list" :href="route('inventory.movements.index')" :current="request()->routeIs('inventory.*')" wire:navigate>
                        {{ __('Inventory') }}
                    </flux:sidebar.item>
                {{-- </flux:sidebar.group> --}}

                <div class="flex flex-col in-data-flux-sidebar-collapsed-desktop:hidden" data-flux-sidebar-group>
                    <div class="px-3 py-2">
                        <a
                            href="{{ route('procurement.index') }}"
                            wire:navigate
                            @class([
                                'text-sm font-medium leading-none transition-colors',
                                'text-zinc-800 dark:text-white' => request()->routeIs('procurement.index'),
                                'text-zinc-400 hover:text-zinc-800 dark:hover:text-white' => ! request()->routeIs('procurement.index'),
                            ])
                        >
                            {{ __('Procurement') }}
                        </a>
                    </div>

                    <div class="flex flex-col">
                        <flux:sidebar.item icon="document-text" :href="route('procurement.rfqs.index')" :current="request()->routeIs('procurement.rfqs.*')" wire:navigate>
                            {{ __('Request For Quotations') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="shopping-cart" :href="route('procurement.purchase-orders.index')" :current="request()->routeIs('procurement.purchase-orders.*')" wire:navigate>
                            {{ __('Purchase Orders') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="arrow-down-tray" :href="route('procurement.goods-receipts.index')" :current="request()->routeIs('procurement.goods-receipts.*')" wire:navigate>
                            {{ __('Receipts (Stock In)') }}
                        </flux:sidebar.item>
                    </div>
                </div>

                <flux:sidebar.group :heading="__('Sales')" class="grid">
                    <flux:sidebar.item icon="clipboard-document-check" :href="route('sales.orders.index')" :current="request()->routeIs('sales.orders.*')" wire:navigate>
                        {{ __('Sales Orders') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Accounting')" class="grid">
                    <flux:sidebar.item icon="banknotes" :href="route('accounting.payables.index')" :current="request()->routeIs('accounting.payables.*')" wire:navigate>
                        {{ __('Accounts Payable') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="currency-dollar" :href="route('accounting.receivables.index')" :current="request()->routeIs('accounting.receivables.*')" wire:navigate>
                        {{ __('Accounts Receivable') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="arrow-down-tray" :href="route('accounting.supplier-payments.create')" :current="request()->routeIs('accounting.supplier-payments.*')" wire:navigate>
                        {{ __('Supplier Payments') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="queue-list" :href="route('accounting.payment-runs.index')" :current="request()->routeIs('accounting.payment-runs.*')" wire:navigate>
                        {{ __('Payment Runs') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="arrow-up-tray" :href="route('accounting.customer-payments.create')" :current="request()->routeIs('accounting.customer-payments.*')" wire:navigate>
                        {{ __('Customer Payments') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Masterlists')" class="grid">
                    <flux:sidebar.item icon="archive-box" :href="route('products.index')" :current="request()->routeIs('products.*')" wire:navigate>
                        {{ __('Products') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="building-storefront" :href="route('suppliers.index')" :current="request()->routeIs('suppliers.*')" wire:navigate>
                        {{ __('Suppliers') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="users" :href="route('customers.index')" :current="request()->routeIs('customers.*')" wire:navigate>
                        {{ __('Customers') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="building-library" :href="route('bank-accounts.index')" :current="request()->routeIs('bank-accounts.*')" wire:navigate>
                        {{ __('Bank Accounts') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            {{-- <flux:sidebar.nav>
                <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                    {{ __('Repository') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
                    {{ __('Documentation') }}
                </flux:sidebar.item>
            </flux:sidebar.nav> --}}

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
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
                    </flux:menu.radio.group>

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
