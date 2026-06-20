<?php

use App\Enums\AccountingOpenItemStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Models\AccountsPayable;
use App\Models\AccountsReceivable;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Dashboard'])]
#[Title('Dashboard')]
class extends Component {

    public function getTenantIdProperty(): int
    {
        return (int) session('current_tenant_id');
    }

    public function getTotalProductsProperty(): int
    {
        return Product::query()
            ->where('tenant_id', $this->tenantId)
            ->count();
    }

    public function getLowStockProductsProperty(): int
    {
        return Product::query()
            ->where('tenant_id', $this->tenantId)
            ->whereNotNull('reorder_point')
            ->whereColumn('reorder_point', '>', DB::raw('COALESCE(
                (SELECT SUM(quantity) FROM inventory_movements WHERE product_id = products.id),
                0
            )'))
            ->count();
    }

    public function getOpenPurchaseOrdersProperty(): int
    {
        return PurchaseOrder::query()
            ->where('tenant_id', $this->tenantId)
            ->whereIn('status', [
                PurchaseOrderStatus::Confirmed->value,
                PurchaseOrderStatus::PartiallyReceived->value,
            ])
            ->count();
    }

    public function getOpenSalesOrdersProperty(): int
    {
        return SalesOrder::query()
            ->where('tenant_id', $this->tenantId)
            ->whereIn('status', [
                SalesOrderStatus::Confirmed->value,
                SalesOrderStatus::PartiallyFulfilled->value,
            ])
            ->count();
    }

    public function getOutstandingPayablesProperty(): float
    {
        return (float) AccountsPayable::query()
            ->where('tenant_id', $this->tenantId)
            ->whereIn('status', [
                AccountingOpenItemStatus::Open->value,
                AccountingOpenItemStatus::Partial->value,
            ])
            ->sum(DB::raw('total_amount - amount_paid'));
    }

    public function getOutstandingReceivablesProperty(): float
    {
        return (float) AccountsReceivable::query()
            ->where('tenant_id', $this->tenantId)
            ->whereIn('status', [
                AccountingOpenItemStatus::Open->value,
                AccountingOpenItemStatus::Partial->value,
            ])
            ->sum(DB::raw('total_amount - amount_paid'));
    }

    /** @return Collection<int, InventoryMovement> */
    public function getRecentMovementsProperty(): Collection
    {
        return InventoryMovement::query()
            ->where('tenant_id', $this->tenantId)
            ->with('product:id,name,sku')
            ->latest()
            ->limit(8)
            ->get();
    }

    /** @return Collection<int, PurchaseOrder> */
    public function getRecentPurchaseOrdersProperty(): Collection
    {
        return PurchaseOrder::query()
            ->where('tenant_id', $this->tenantId)
            ->with('supplier:id,name')
            ->latest()
            ->limit(5)
            ->get();
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-8">

    {{-- Page heading --}}
    <div>
        <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
        <flux:text class="mt-1">
            {{ __('Overview of your business activity across procurement, inventory, sales, and accounting.') }}
        </flux:text>
    </div>

    {{-- KPI Stat Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">

        {{-- Products --}}
        <a href="{{ route('products.index') }}" wire:navigate
            class="group flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:border-zinc-600">
            <div class="flex items-center justify-between">
                <flux:text class="text-xs font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">
                    {{ __('Products') }}
                </flux:text>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-100 dark:bg-violet-900/40">
                    <flux:icon name="archive-box" class="h-4 w-4 text-violet-600 dark:text-violet-400" />
                </span>
            </div>
            <div class="flex items-end gap-2">
                <span class="text-3xl font-bold tabular-nums text-zinc-900 dark:text-white">
                    {{ number_format($this->totalProducts) }}
                </span>
            </div>
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                @if ($this->lowStockProducts > 0)
                    <span class="font-semibold text-amber-600 dark:text-amber-400">
                        {{ $this->lowStockProducts }} {{ __('below reorder point') }}
                    </span>
                @else
                    {{ __('All stock levels healthy') }}
                @endif
            </flux:text>
        </a>

        {{-- Open Purchase Orders --}}
        <a href="{{ route('procurement.purchase-orders.index') }}" wire:navigate
            class="group flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:border-zinc-600">
            <div class="flex items-center justify-between">
                <flux:text class="text-xs font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">
                    {{ __('Open POs') }}
                </flux:text>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/40">
                    <flux:icon name="shopping-cart" class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                </span>
            </div>
            <div class="flex items-end gap-2">
                <span class="text-3xl font-bold tabular-nums text-zinc-900 dark:text-white">
                    {{ number_format($this->openPurchaseOrders) }}
                </span>
            </div>
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('Confirmed or partially received') }}
            </flux:text>
        </a>

        {{-- Open Sales Orders --}}
        <a href="{{ route('sales.orders.index') }}" wire:navigate
            class="group flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:border-zinc-600">
            <div class="flex items-center justify-between">
                <flux:text class="text-xs font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">
                    {{ __('Open Sales Orders') }}
                </flux:text>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/40">
                    <flux:icon name="clipboard-document-check" class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                </span>
            </div>
            <div class="flex items-end gap-2">
                <span class="text-3xl font-bold tabular-nums text-zinc-900 dark:text-white">
                    {{ number_format($this->openSalesOrders) }}
                </span>
            </div>
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('Confirmed or partially fulfilled') }}
            </flux:text>
        </a>

        {{-- Outstanding Payables --}}
        <a href="{{ route('accounting.payables.index') }}" wire:navigate
            class="group flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:border-zinc-600">
            <div class="flex items-center justify-between">
                <flux:text class="text-xs font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">
                    {{ __('Payables Due') }}
                </flux:text>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-100 dark:bg-rose-900/40">
                    <flux:icon name="banknotes" class="h-4 w-4 text-rose-600 dark:text-rose-400" />
                </span>
            </div>
            <div class="flex items-end gap-2">
                <span class="text-3xl font-bold tabular-nums text-zinc-900 dark:text-white">
                    ₱{{ number_format($this->outstandingPayables, 2) }}
                </span>
            </div>
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('Open & partially paid invoices') }}
            </flux:text>
        </a>

        {{-- Outstanding Receivables --}}
        <a href="{{ route('accounting.receivables.index') }}" wire:navigate
            class="group flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:border-zinc-600">
            <div class="flex items-center justify-between">
                <flux:text class="text-xs font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">
                    {{ __('Receivables Due') }}
                </flux:text>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/40">
                    <flux:icon name="currency-dollar" class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                </span>
            </div>
            <div class="flex items-end gap-2">
                <span class="text-3xl font-bold tabular-nums text-zinc-900 dark:text-white">
                    ₱{{ number_format($this->outstandingReceivables, 2) }}
                </span>
            </div>
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('Open & partially collected') }}
            </flux:text>
        </a>

    </div>

    {{-- Main content: movements + recent POs --}}
    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Recent Inventory Movements --}}
        <div class="lg:col-span-2">
            <flux:card class="flex flex-col overflow-hidden p-0 bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40">
                <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-white/10">
                    <div>
                        <flux:heading size="lg">{{ __('Recent Inventory Movements') }}</flux:heading>
                        <flux:text class="mt-0.5 text-sm">{{ __('Latest 8 stock changes across all products.') }}</flux:text>
                    </div>
                    <flux:button size="sm" variant="ghost" :href="route('inventory.movements.index')" wire:navigate>
                        {{ __('View all') }}
                    </flux:button>
                </div>

                @if ($this->recentMovements->isEmpty())
                    <div class="p-6">
                        <flux:callout icon="arrows-right-left" color="zinc" inline
                            :heading="__('No movements yet')"
                            :text="__('Stock changes will appear here once procurement or sales documents are posted.')" />
                    </div>
                @else
                    <flux:table>
                        <flux:table.columns class="bg-neutral-200 dark:bg-neutral-600">
                            <flux:table.column class="px-6!">{{ __('Product') }}</flux:table.column>
                            <flux:table.column class="px-6!">{{ __('Type') }}</flux:table.column>
                            <flux:table.column align="end" class="px-6!">{{ __('Qty') }}</flux:table.column>
                            <flux:table.column class="px-6!">{{ __('When') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->recentMovements as $movement)
                                <flux:table.row :key="$movement->id">
                                    <flux:table.cell class="px-6!">
                                        <p class="font-medium text-zinc-900 dark:text-white">{{ $movement->product->name }}</p>
                                        @if ($movement->product->sku)
                                            <p class="font-mono text-xs text-zinc-400">{{ $movement->product->sku }}</p>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="px-6!">
                                        <flux:badge
                                            :color="$movement->movement_type->fluxBadgeColor()"
                                            size="sm"
                                            inset="top bottom"
                                            class="capitalize"
                                        >
                                            {{ $movement->movement_type->value }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="px-6!">
                                        <span @class([
                                            'tabular-nums font-semibold text-sm',
                                            'text-emerald-600 dark:text-emerald-400' => (float) $movement->quantity > 0,
                                            'text-rose-600 dark:text-rose-400'       => (float) $movement->quantity < 0,
                                            'text-zinc-700 dark:text-zinc-300'       => (float) $movement->quantity == 0,
                                        ])>
                                            {{ \Illuminate\Support\Number::format((float) $movement->quantity, maxPrecision: 4) }}
                                        </span>
                                    </flux:table.cell>
                                    <flux:table.cell class="px-6! text-zinc-500 dark:text-zinc-400 text-sm">
                                        {{ $movement->created_at->diffForHumans() }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>
        </div>

        {{-- Recent Purchase Orders --}}
        <div class="lg:col-span-1">
            <flux:card class="flex flex-col overflow-hidden p-0 bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40">
                <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-white/10">
                    <div>
                        <flux:heading size="lg">{{ __('Recent POs') }}</flux:heading>
                        <flux:text class="mt-0.5 text-sm">{{ __('Latest purchase orders.') }}</flux:text>
                    </div>
                    <flux:button size="sm" variant="ghost" :href="route('procurement.purchase-orders.index')" wire:navigate>
                        {{ __('View all') }}
                    </flux:button>
                </div>

                @if ($this->recentPurchaseOrders->isEmpty())
                    <div class="p-6">
                        <flux:callout icon="shopping-cart" color="zinc" inline
                            :heading="__('No purchase orders yet')"
                            :text="__('Create a PO from an approved RFQ.')" />
                    </div>
                @else
                    <div class="divide-y divide-zinc-200 dark:divide-white/10">
                        @foreach ($this->recentPurchaseOrders as $po)
                            <a
                                href="{{ route('procurement.purchase-orders.show', $po->id) }}"
                                wire:navigate
                                class="flex flex-col gap-1 px-6 py-4 transition hover:bg-zinc-50 dark:hover:bg-white/5"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-mono text-xs font-semibold text-zinc-700 dark:text-zinc-200">
                                        {{ $po->reference_code ?? __('—') }}
                                    </span>
                                    <flux:badge
                                        size="sm"
                                        inset="top bottom"
                                        :color="match($po->status) {
                                            \App\Enums\PurchaseOrderStatus::Confirmed          => 'blue',
                                            \App\Enums\PurchaseOrderStatus::PartiallyReceived  => 'amber',
                                            \App\Enums\PurchaseOrderStatus::Received           => 'green',
                                            \App\Enums\PurchaseOrderStatus::Cancelled          => 'zinc',
                                        }"
                                        class="capitalize"
                                    >
                                        {{ str_replace('_', ' ', $po->status->value) }}
                                    </flux:badge>
                                </div>
                                <flux:text class="truncate text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ $po->supplier->name ?? __('Unknown supplier') }}
                                </flux:text>
                                <flux:text class="text-xs text-zinc-400">
                                    {{ $po->order_date?->translatedFormat('M j, Y') ?? __('No date') }}
                                </flux:text>
                            </a>
                        @endforeach
                    </div>
                @endif
            </flux:card>
        </div>

    </div>

    {{-- Module Quick Links --}}
    <div>
        <flux:heading size="lg" class="mb-4">{{ __('Quick Access') }}</flux:heading>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">

            <a href="{{ route('procurement.index') }}" wire:navigate
                class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 transition hover:border-amber-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:border-amber-700">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/40">
                    <flux:icon name="shopping-cart" class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                </span>
                <div>
                    <p class="font-semibold text-zinc-900 dark:text-white">{{ __('Procurement') }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('RFQs, POs, Receipts') }}</p>
                </div>
            </a>

            <a href="{{ route('inventory.movements.index') }}" wire:navigate
                class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 transition hover:border-violet-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:border-violet-700">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-violet-100 dark:bg-violet-900/40">
                    <flux:icon name="clipboard-document-list" class="h-5 w-5 text-violet-600 dark:text-violet-400" />
                </span>
                <div>
                    <p class="font-semibold text-zinc-900 dark:text-white">{{ __('Inventory') }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Movements & Adjustments') }}</p>
                </div>
            </a>

            <a href="{{ route('sales.orders.index') }}" wire:navigate
                class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 transition hover:border-blue-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:border-blue-700">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/40">
                    <flux:icon name="clipboard-document-check" class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                </span>
                <div>
                    <p class="font-semibold text-zinc-900 dark:text-white">{{ __('Sales') }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Orders, Shipments, Invoices') }}</p>
                </div>
            </a>

            <a href="{{ route('accounting.payables.index') }}" wire:navigate
                class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 transition hover:border-emerald-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:border-emerald-700">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/40">
                    <flux:icon name="banknotes" class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                </span>
                <div>
                    <p class="font-semibold text-zinc-900 dark:text-white">{{ __('Accounting') }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Payables & Receivables') }}</p>
                </div>
            </a>

        </div>
    </div>

</div>
