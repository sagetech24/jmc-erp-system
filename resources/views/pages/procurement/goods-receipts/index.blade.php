<?php

use App\Domains\Crm\Services\SearchSuppliersService;
use App\Domains\Procurement\Services\ListGoodsReceiptsForTenantService;
use App\Domains\Procurement\Services\SummarizeGoodsReceiptRegisterService;
use App\Livewire\Concerns\InteractsWithSearchableSelects;
use App\Models\GoodsReceipt;
use App\Models\Supplier;
use App\Support\TenantMoney;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app', ['title' => 'Goods receipts'])]
#[Title('Goods receipts')]
class extends Component {
    use InteractsWithSearchableSelects;
    use WithPagination;

    #[Url(as: 'supplier', history: true, except: '')]
    public string $supplierFilter = '';

    public string $supplierFilterSearch = '';

    #[Url(as: 'status', history: true, except: '')]
    public string $statusFilter = '';

    #[Url(as: 'from', history: true, except: '')]
    public string $receivedFrom = '';

    #[Url(as: 'to', history: true, except: '')]
    public string $receivedTo = '';

    #[Url(as: 'po', history: true, except: '')]
    public string $poSearch = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', GoodsReceipt::class);

        if ($this->supplierFilter !== '') {
            $supplier = Supplier::query()
                ->where('tenant_id', (int) session('current_tenant_id'))
                ->whereKey((int) $this->supplierFilter)
                ->first(['name']);
            if ($supplier !== null) {
                $this->supplierFilterSearch = $supplier->name;
            }
        }
    }

    public function updatedSupplierFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedReceivedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedReceivedTo(): void
    {
        $this->resetPage();
    }

    public function updatedPoSearch(): void
    {
        $this->resetPage();
    }

    public function applyPresetLast30Days(): void
    {
        $this->receivedFrom = now()->subDays(29)->toDateString();
        $this->receivedTo = now()->toDateString();
        $this->resetPage();
    }

    public function applyPresetThisMonth(): void
    {
        $this->receivedFrom = now()->startOfMonth()->toDateString();
        $this->receivedTo = now()->toDateString();
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('supplierFilter', 'supplierFilterSearch', 'statusFilter', 'receivedFrom', 'receivedTo', 'poSearch');
        $this->resetPage();
    }

    public function getSupplierFilterResultsProperty(): Collection
    {
        $tenantId = (int) session('current_tenant_id');

        return app(SearchSuppliersService::class)->execute(
            $tenantId,
            $this->supplierFilterSearch,
            $this->supplierFilter !== '' ? (int) $this->supplierFilter : null,
        );
    }

    public function getReceiptsProperty()
    {
        $tenantId = (int) session('current_tenant_id');
        $filters = [
            'supplier_id' => $this->supplierFilter,
            'status' => $this->statusFilter,
            'received_from' => $this->receivedFrom,
            'received_to' => $this->receivedTo,
            'po_reference' => $this->poSearch,
        ];

        return app(ListGoodsReceiptsForTenantService::class)
            ->paginate($tenantId, 12, $filters)
            ->withPath(route('procurement.goods-receipts.index', absolute: false))
            ->withQueryString();
    }

    /**
     * @return array{receipt_count: int, awaiting_accounts_payable: int, extended_value: string}
     */
    public function getSummaryProperty(): array
    {
        $tenantId = (int) session('current_tenant_id');
        $filters = [
            'supplier_id' => $this->supplierFilter,
            'status' => $this->statusFilter,
            'received_from' => $this->receivedFrom,
            'received_to' => $this->receivedTo,
            'po_reference' => $this->poSearch,
        ];

        return app(SummarizeGoodsReceiptRegisterService::class)->execute($tenantId, $filters);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Receipts (Stock In) Management') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Posted receipts from purchase orders: inventory movements, landed costs, and accounts payable readiness.') }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button variant="primary" :href="route('procurement.purchase-orders.index')" wire:navigate>
                {{ __('Receive from purchase order') }}
            </flux:button>
        </div>
    </div>

    @php
        $summary = $this->summary;
    @endphp

    <div class="grid gap-4 sm:grid-cols-3">
        <flux:card class="border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Receipts in view') }}</flux:text>
            <flux:heading size="lg" class="mt-2 tabular-nums">{{ number_format($summary['receipt_count']) }}</flux:heading>
            <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Matches your filters below.') }}</flux:text>
        </flux:card>
        <flux:card class="border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Extended value (at capture)') }}</flux:text>
            <flux:heading size="lg" class="mt-2 tabular-nums">{{ TenantMoney::format((float) $summary['extended_value'], null, 2) }}</flux:heading>
            <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Σ qty × resolved unit cost per line (same basis as AP posting).') }}</flux:text>
        </flux:card>
        <flux:card class="border border-amber-200/80 bg-amber-50/50 p-5 dark:border-amber-900/40 dark:bg-amber-950/20">
            <flux:text class="text-xs font-medium uppercase tracking-wide text-amber-800 dark:text-amber-200">{{ __('Posted, no AP yet') }}</flux:text>
            <flux:heading size="lg" class="mt-2 tabular-nums text-amber-950 dark:text-amber-100">{{ number_format($summary['awaiting_accounts_payable']) }}</flux:heading>
            <flux:text class="mt-1 text-xs text-amber-900/80 dark:text-amber-200/90">{{ __('Post payables from receipt detail or the AP workspace when ready.') }}</flux:text>
        </flux:card>
    </div>

    <flux:card class="flex flex-col overflow-hidden border border-zinc-300 bg-neutral-100 p-0 dark:border-zinc-300/40 dark:bg-neutral-700">
        <div class="border-b border-zinc-200 px-6 py-5 dark:border-white/10">
            <flux:heading size="lg">{{ __('Filters') }}</flux:heading>
            <flux:text class="mt-1 text-sm">{{ __('Narrow by supplier, PO reference, receipt status, or received date.') }}</flux:text>
        </div>
        <div class="flex flex-col gap-4 p-6">
            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" variant="outline" wire:click="applyPresetLast30Days">{{ __('Last 30 days') }}</flux:button>
                <flux:button size="sm" variant="outline" wire:click="applyPresetThisMonth">{{ __('This month') }}</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
            </div>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <x-searchable-select
                    wire:model.live="supplierFilter"
                    search-wire="supplierFilterSearch"
                    value-property="supplierFilter"
                    search-property="supplierFilterSearch"
                    :label="__('Supplier')"
                    :placeholder="__('Search suppliers…')"
                    display-format="supplier"
                    :results="$this->supplierFilterResults"
                    :clearable="true"
                    :empty-label="__('All suppliers')"
                />
                <flux:select wire:model.live="statusFilter" :label="__('Status')">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="{{ \App\Enums\GoodsReceiptStatus::Posted->value }}">{{ __('Posted') }}</option>
                    <option value="{{ \App\Enums\GoodsReceiptStatus::Draft->value }}">{{ __('Draft') }}</option>
                </flux:select>
                <flux:input wire:model.live.debounce.400ms="poSearch" :label="__('Purchase order ref.')" :placeholder="__('e.g. PO-2024-')" />
                <flux:input wire:model.live="receivedFrom" type="date" :label="__('Received from')" />
                <flux:input wire:model.live="receivedTo" type="date" :label="__('Received to')" />
            </div>
        </div>
    </flux:card>

    <div class="min-w-0 w-full">
        <flux:card class="flex flex-col overflow-hidden border border-zinc-300 bg-neutral-100 p-0 dark:border-zinc-300/40 dark:bg-neutral-700">
            <div class="border-b border-zinc-200 px-6 py-5 dark:border-white/10">
                <flux:heading size="lg">{{ __('Receipt documents') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('Each row is a posted document tied to one purchase order. Open a receipt for line detail, stock traceability, and payable posting.') }}</flux:text>
            </div>

            @if ($this->receipts->isEmpty())
                <div class="p-6">
                    <flux:callout
                        icon="arrow-down-tray"
                        color="zinc"
                        inline
                        :heading="__('No receipts match your filters')"
                        :text="__('Post a receipt from an open purchase order, or widen the date range and filters.')"
                    />
                </div>
            @else
                <flux:table>
                    <flux:table.columns sticky class="bg-neutral-200 dark:bg-neutral-600">
                        <flux:table.column class="px-6!">{{ __('Receipt') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Purchase order') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Supplier') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Received') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Status') }}</flux:table.column>
                        <flux:table.column align="end" class="px-6!">{{ __('Lines') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Accounts payable') }}</flux:table.column>
                        <flux:table.column align="end" class="w-0 whitespace-nowrap px-6!">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->receipts as $gr)
                            <flux:table.row :key="'gr-'.$gr->id">
                                <flux:table.cell variant="strong" class="px-6! font-mono">#{{ $gr->id }}</flux:table.cell>
                                <flux:table.cell class="px-6! font-mono">{{ $gr->purchaseOrder->reference_code }}</flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    <span class="rounded-md border border-zinc-200 bg-zinc-300 px-2 py-1 text-xs font-medium text-zinc-700 dark:border-zinc-700 dark:bg-zinc-700/50 dark:text-zinc-200">
                                        {{ $gr->purchaseOrder->supplier->name }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    <span class="tabular-nums text-zinc-700 dark:text-zinc-200">{{ $gr->received_at->translatedFormat('M j, Y — g:i A') }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    @php
                                        $st = $gr->status;
                                        $statusColor = $st === \App\Enums\GoodsReceiptStatus::Posted
                                            ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                                            : 'bg-zinc-100 text-zinc-700 border-zinc-200';
                                    @endphp
                                    <span class="{{ $statusColor }} rounded-md border px-2 py-1 text-xs font-medium capitalize dark:border-zinc-600">{{ $st->value }}</span>
                                </flux:table.cell>
                                <flux:table.cell align="end" class="px-6! tabular-nums">{{ $gr->lines_count }}</flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    @if ($gr->accountsPayable)
                                        <flux:badge color="blue" size="sm">{{ __('Posted') }}</flux:badge>
                                    @elseif ($gr->status === \App\Enums\GoodsReceiptStatus::Posted)
                                        <flux:badge color="amber" size="sm">{{ __('Open') }}</flux:badge>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end" class="px-6!">
                                    <flux:button
                                        size="xs"
                                        variant="outline"
                                        :href="route('procurement.goods-receipts.show', $gr->id)"
                                        wire:navigate
                                        class="cursor-pointer border border-zinc-200 text-xs! dark:border-white/40"
                                    >
                                        {{ __('View') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>

        @if (! $this->receipts->isEmpty() && $this->receipts->hasPages())
            <div class="mt-4 flex justify-between px-1 sm:px-0 items-center gap-4">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 w-full">
                    {{ __('Showing') }} {{ $this->receipts->firstItem() }} {{ __('to') }} {{ $this->receipts->lastItem() }} {{ __('of') }} {{ $this->receipts->total() }} {{ __('entries') }}
                </flux:text>
                {{ $this->receipts->links('vendor.pagination.numbers-only') }}
            </div>
        @endif
    </div>
</div>
