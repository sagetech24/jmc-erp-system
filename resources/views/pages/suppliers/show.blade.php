<?php

use App\Domains\Crm\Services\GetSupplierDashboardMetricsService;
use App\Enums\SupplierStatus;
use App\Models\AccountsPayable;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Support\TenantMoney;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Supplier'])]
#[Title('Supplier')]
class extends Component {
    public Supplier $supplier;

    #[Url(as: 'tab', history: true)]
    public string $tab = 'overview';

    /** @var array<string, mixed> */
    public array $metrics = [];

    public function mount(int $id, GetSupplierDashboardMetricsService $metricsService): void
    {
        $tenantId = (int) session('current_tenant_id');
        $this->supplier = Supplier::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->firstOrFail();

        Gate::authorize('view', $this->supplier);

        $this->metrics = $metricsService->execute($tenantId, $this->supplier);

        $this->tab = $this->normalizeTab($this->tab);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $this->normalizeTab($tab);
    }

    private function normalizeTab(string $tab): string
    {
        $allowed = ['overview', 'purchase_orders', 'goods_receipts', 'ap', 'payments'];

        return in_array($tab, $allowed, true) ? $tab : 'overview';
    }

    /**
     * @return Collection<int, PurchaseOrder>
     */
    public function getPurchaseOrdersProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('supplier_id', $this->supplier->id)
            ->with('lines')
            ->latest('order_date')
            ->latest('id')
            ->limit(50)
            ->get();
    }

    /**
     * @return Collection<int, GoodsReceipt>
     */
    public function getGoodsReceiptsProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return GoodsReceipt::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('purchaseOrder', fn ($q) => $q->where('supplier_id', $this->supplier->id))
            ->with(['purchaseOrder', 'accountsPayable'])
            ->withCount('lines')
            ->latest('received_at')
            ->limit(50)
            ->get();
    }

    /**
     * @return Collection<int, AccountsPayable>
     */
    public function getAccountsPayablesProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return AccountsPayable::query()
            ->where('tenant_id', $tenantId)
            ->where('supplier_id', $this->supplier->id)
            ->with('goodsReceipt')
            ->latest('posted_at')
            ->limit(50)
            ->get();
    }

    /**
     * @return Collection<int, SupplierPayment>
     */
    public function getSupplierPaymentsProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return SupplierPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('supplier_id', $this->supplier->id)
            ->latest('paid_at')
            ->limit(50)
            ->get();
    }
}; ?>

@php
    $tabClass = 'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition';
    $tabActive = 'bg-white text-zinc-900 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-100 dark:ring-white/10';
    $tabIdle = 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100';
    $buttonClass = 'cursor-pointer border border-zinc-200 dark:border-zinc-700';
@endphp

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:heading size="xl">{{ $supplier->name }}</flux:heading>
                @if ($supplier->code)
                    <flux:badge color="zinc" size="sm" class="font-mono">{{ $supplier->code }}</flux:badge>
                @endif
                @if ($supplier->status === SupplierStatus::Active)
                    <flux:badge color="green" size="sm">{{ $supplier->status->label() }}</flux:badge>
                @elseif ($supplier->status === SupplierStatus::OnHold)
                    <flux:badge color="amber" size="sm">{{ $supplier->status->label() }}</flux:badge>
                @else
                    <flux:badge color="red" size="sm">{{ $supplier->status->label() }}</flux:badge>
                @endif
            </div>
            <flux:text class="mt-1">{{ __('Vendor profile, procurement activity, and accounts payable in one place.') }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('create', \App\Models\Rfq::class)
                <flux:button variant="filled" class="{{ $buttonClass }}" :href="route('procurement.rfqs.create', ['supplier_id' => $supplier->id])" wire:navigate>
                    {{ __('Create Purchase Request') }}
                </flux:button>
            @endcan
            @can('create', \App\Models\PurchaseOrder::class)
                <flux:button variant="filled" class="{{ $buttonClass }}" :href="route('procurement.purchase-orders.create', ['supplier_id' => $supplier->id])" wire:navigate>
                    {{ __('Create Purchase Order') }}
                </flux:button>
            @endcan
            @can('create', SupplierPayment::class)
                <flux:button variant="filled" class="{{ $buttonClass }}" :href="route('accounting.supplier-payments.create', ['supplier_id' => $supplier->id])" wire:navigate>
                    {{ __('Record payment') }}
                </flux:button>
            @endcan
            <flux:button variant="filled" class="{{ $buttonClass }}" :href="route('suppliers.index')" wire:navigate>
                <flux:icon name="arrow-left" class="w-4 h-4" />
                {{ __('Back') }}
            </flux:button>
        </div>
    </div>

    <div class="flex flex-wrap gap-1 rounded-xl bg-zinc-100 p-1 dark:bg-zinc-800/80">
        <button type="button" wire:click="setTab('overview')" class="{{ $tabClass }} {{ $tab === 'overview' ? $tabActive : $tabIdle }}">
            {{ __('Overview') }}
        </button>
        <button type="button" wire:click="setTab('purchase_orders')" class="{{ $tabClass }} {{ $tab === 'purchase_orders' ? $tabActive : $tabIdle }}">
            {{ __('Purchase Orders') }}
        </button>
        <button type="button" wire:click="setTab('goods_receipts')" class="{{ $tabClass }} {{ $tab === 'goods_receipts' ? $tabActive : $tabIdle }}">
            {{ __('Goods Receipts') }}
        </button>
        <button type="button" wire:click="setTab('ap')" class="{{ $tabClass }} {{ $tab === 'ap' ? $tabActive : $tabIdle }}">
            {{ __('Accounts Payables') }}
        </button>
        <button type="button" wire:click="setTab('payments')" class="{{ $tabClass }} {{ $tab === 'payments' ? $tabActive : $tabIdle }}">
            {{ __('Payments') }}
        </button>
    </div>

    @if ($tab === 'overview')
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:card class="p-4">
                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Open AP balance') }}</flux:text>
                <flux:heading size="lg" class="mt-1 tabular-nums">
                    {{ TenantMoney::format((float) $metrics['open_ap_balance']) }}
                </flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('YTD spend (posted AP)') }}</flux:text>
                <flux:heading size="lg" class="mt-1 tabular-nums">
                    {{ TenantMoney::format((float) $metrics['ytd_spend_posted']) }}
                </flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('YTD PO value') }}</flux:text>
                <flux:heading size="lg" class="mt-1 tabular-nums">
                    {{ TenantMoney::format((float) $metrics['ytd_po_value']) }}
                </flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Last activity') }}</flux:text>
                <flux:text class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">
                    {{ __('Last PO:') }}
                    @if ($metrics['last_po_date'])
                        {{ $metrics['last_po_date']->translatedFormat('F j, Y') }}
                    @else
                        —
                    @endif
                </flux:text>
                <flux:text class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                    {{ __('Last payment:') }}
                    @if ($metrics['last_payment_at'])
                        {{ $metrics['last_payment_at']->translatedFormat('M j, Y — g:i A') }}
                    @else
                        —
                    @endif
                </flux:text>
            </flux:card>
        </div>

        <flux:card class="p-6">
            <flux:heading size="lg">{{ __('Aging (open AP by days since posted)') }}</flux:heading>
            <flux:text class="mt-1 text-sm">{{ __('Based on posted date; add due dates later for calendar aging.') }}</flux:text>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:text class="text-xs text-zinc-500">0–30 {{ __('days') }}</flux:text>
                    <flux:text class="mt-1 tabular-nums font-semibold">{{ TenantMoney::format((float) $metrics['aging_0_30']) }}</flux:text>
                </div>
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:text class="text-xs text-zinc-500">31–60 {{ __('days') }}</flux:text>
                    <flux:text class="mt-1 tabular-nums font-semibold">{{ TenantMoney::format((float) $metrics['aging_31_60']) }}</flux:text>
                </div>
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:text class="text-xs text-zinc-500">61–90 {{ __('days') }}</flux:text>
                    <flux:text class="mt-1 tabular-nums font-semibold">{{ TenantMoney::format((float) $metrics['aging_61_90']) }}</flux:text>
                </div>
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:text class="text-xs text-zinc-500">90+ {{ __('days') }}</flux:text>
                    <flux:text class="mt-1 tabular-nums font-semibold">{{ TenantMoney::format((float) $metrics['aging_over_90']) }}</flux:text>
                </div>
            </div>
        </flux:card>

        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card class="p-6">
                <flux:heading size="lg">{{ __('Contact') }}</flux:heading>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Email') }}</dt>
                        <dd class="text-end text-zinc-900 dark:text-zinc-100">{{ $supplier->email ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Phone') }}</dt>
                        <dd class="text-end text-zinc-900 dark:text-zinc-100">{{ $supplier->phone ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Address') }}</dt>
                        <dd class="max-w-xs text-end text-zinc-900 dark:text-zinc-100">{{ $supplier->address ?: '—' }}</dd>
                    </div>
                </dl>
            </flux:card>
            <flux:card class="p-6">
                <flux:heading size="lg">{{ __('Commercial & tax') }}</flux:heading>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Payment terms') }}</dt>
                        <dd class="text-end text-zinc-900 dark:text-zinc-100">{{ $supplier->payment_terms ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Tax / VAT ID') }}</dt>
                        <dd class="text-end font-mono text-zinc-900 dark:text-zinc-100">{{ $supplier->tax_id ?: '—' }}</dd>
                    </div>
                </dl>
            </flux:card>
        </div>

        @if ($supplier->notes)
            <flux:card class="p-6">
                <flux:heading size="lg">{{ __('Notes') }}</flux:heading>
                <flux:text class="mt-2 whitespace-pre-wrap text-sm text-zinc-700 dark:text-zinc-300">{{ $supplier->notes }}</flux:text>
            </flux:card>
        @endif

        @can('update', $supplier)
            <flux:card class="p-6">
                <flux:heading size="lg">{{ __('Edit from list') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('Master data changes are made from the suppliers list.') }}</flux:text>
                <flux:button class="mt-4 {{ $buttonClass }}" variant="filled" :href="route('suppliers.index')" wire:navigate>{{ __('Go to suppliers') }}</flux:button>
            </flux:card>
        @endcan
    @endif

    @if ($tab === 'purchase_orders')
        <flux:card class="overflow-hidden p-0">
            @if ($this->purchaseOrders->isEmpty())
                <div class="p-8">
                    <flux:callout icon="document-text" color="zinc" inline :heading="__('No purchase orders')" :text="__('Create a purchase order for this supplier.')" />
                </div>
            @else
                <flux:table>
                    <flux:table.columns sticky class="bg-neutral-200 dark:bg-neutral-600">
                        <flux:table.column class="px-6!">{{ __('Reference') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Date') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Status') }}</flux:table.column>
                        <flux:table.column align="end" class="px-6!">{{ __('Lines') }}</flux:table.column>
                        <flux:table.column align="end" class="w-0 whitespace-nowrap px-6!"></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->purchaseOrders as $po)
                            <flux:table.row :key="'po-'.$po->id">
                                <flux:table.cell variant="strong" class="px-6! font-mono">{{ $po->reference_code }}</flux:table.cell>
                                <flux:table.cell class="px-6!">{{ $po->order_date->translatedFormat('F j, Y') }}</flux:table.cell>
                                <flux:table.cell class="px-6!">{{ $po->status->value === 'cancelled' ? __('Close PO') : \Illuminate\Support\Str::headline($po->status->value) }}</flux:table.cell>
                                <flux:table.cell align="end" class="px-6! tabular-nums">{{ $po->lines->count() }}</flux:table.cell>
                                <flux:table.cell align="end" class="px-6!">
                                    <flux:button size="xs" variant="primary" class="{{ $buttonClass }}" :href="route('procurement.purchase-orders.show', $po->id)" wire:navigate>
                                        {{ __('View') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif

    @if ($tab === 'goods_receipts')
        <flux:card class="overflow-hidden p-0">
            @if ($this->goodsReceipts->isEmpty())
                <div class="p-8">
                    <flux:callout icon="truck" color="zinc" inline :heading="__('No receipts')" :text="__('Receipts appear when goods are received against purchase orders.')" />
                </div>
            @else
                <flux:table>
                    <flux:table.columns sticky class="bg-neutral-200 dark:bg-neutral-600">
                        <flux:table.column class="px-6!">{{ __('Receipt') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('PO') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Received') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Status') }}</flux:table.column>
                        <flux:table.column align="end" class="px-6!">{{ __('Lines') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Supplier invoice ref.') }}</flux:table.column>
                        <flux:table.column align="end" class="w-0 whitespace-nowrap px-6!"></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->goodsReceipts as $gr)
                            <flux:table.row :key="'gr-'.$gr->id">
                                <flux:table.cell variant="strong" class="px-6! font-mono">#{{ $gr->id }}</flux:table.cell>
                                <flux:table.cell class="px-6! font-mono">{{ $gr->purchaseOrder->reference_code }}</flux:table.cell>
                                <flux:table.cell class="px-6!">{{ $gr->received_at->translatedFormat('M j, Y — g:i A') }}</flux:table.cell>
                                <flux:table.cell class="px-6!">{{ \Illuminate\Support\Str::headline($gr->status->value) }}</flux:table.cell>
                                <flux:table.cell align="end" class="px-6! tabular-nums">{{ $gr->lines_count }}</flux:table.cell>
                                <flux:table.cell class="px-6!">{{ $gr->supplier_invoice_reference ?: '—' }}</flux:table.cell>
                                <flux:table.cell align="end" class="px-6!">
                                    <flux:button size="xs" variant="primary" class="{{ $buttonClass }}" :href="route('procurement.goods-receipts.show', $gr->id)" wire:navigate>
                                        {{ __('View') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif

    @if ($tab === 'ap')
        <flux:card class="overflow-hidden p-0">
            @if ($this->accountsPayables->isEmpty())
                <div class="p-8">
                    <flux:callout icon="banknotes" color="zinc" inline :heading="__('No AP rows')" :text="__('Post goods receipts to accounts payable from the AP workspace.')" />
                </div>
            @else
                <flux:table>
                    <flux:table.columns sticky class="bg-neutral-200 dark:bg-neutral-600">
                        <flux:table.column class="px-6!">{{ __('Posted') }}</flux:table.column>
                        <flux:table.column align="end" class="px-6!">{{ __('Total') }}</flux:table.column>
                        <flux:table.column align="end" class="px-6!">{{ __('Paid') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Status') }}</flux:table.column>
                        <flux:table.column align="end" class="w-0 whitespace-nowrap px-6!"></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->accountsPayables as $ap)
                            <flux:table.row :key="'ap-'.$ap->id">
                                <flux:table.cell class="px-6!">{{ $ap->posted_at?->translatedFormat('M j, Y — g:i A') ?? '—' }}</flux:table.cell>
                                <flux:table.cell align="end" class="px-6! tabular-nums">{{ TenantMoney::format((float) $ap->total_amount, null, 4) }}</flux:table.cell>
                                <flux:table.cell align="end" class="px-6! tabular-nums">{{ TenantMoney::format((float) $ap->amount_paid, null, 4) }}</flux:table.cell>
                                <flux:table.cell class="px-6!">{{ \Illuminate\Support\Str::headline($ap->status->value) }}</flux:table.cell>
                                <flux:table.cell align="end" class="px-6!">
                                    <flux:button size="xs" variant="primary" class="{{ $buttonClass }}" :href="route('accounting.payables.index')" wire:navigate>
                                        {{ __('View') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif

    @if ($tab === 'payments')
        <flux:card class="overflow-hidden p-0">
            @if ($this->supplierPayments->isEmpty())
                <div class="p-8">
                    <flux:callout icon="currency-dollar" color="zinc" inline :heading="__('No payments')" :text="__('Record supplier payments from accounting or the purchase order screen.')" />
                </div>
            @else
                <flux:table>
                    <flux:table.columns sticky class="bg-neutral-200 dark:bg-neutral-600">
                        <flux:table.column class="px-6!">{{ __('Paid at') }}</flux:table.column>
                        <flux:table.column align="end" class="px-6!">{{ __('Amount') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Method') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Reference') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->supplierPayments as $pay)
                            <flux:table.row :key="'pay-'.$pay->id">
                                <flux:table.cell class="px-6!">{{ $pay->paid_at->translatedFormat('M j, Y — g:i A') }}</flux:table.cell>
                                <flux:table.cell align="end" class="px-6! tabular-nums">{{ TenantMoney::format((float) $pay->amount) }}</flux:table.cell>
                                <flux:table.cell class="px-6!">{{ $pay->payment_method->label() }}</flux:table.cell>
                                <flux:table.cell class="px-6!">{{ $pay->reference ?: '—' }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif
</div>
