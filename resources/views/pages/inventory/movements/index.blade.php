<?php

use App\Domains\Inventory\DTOs\InventoryMovementSourceLink;
use App\Domains\Inventory\Services\ExportInventoryMovementsCsvService;
use App\Domains\Inventory\Services\ListInventoryMovementsForTenantService;
use App\Domains\Inventory\Services\ResolveInventoryMovementSourceLinkService;
use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts::app', ['title' => 'Inventory movements'])]
#[Title('Inventory movements')]
class extends Component {
    use WithPagination;

    public string $date_from = '';

    public string $date_to = '';

    public string $movement_type = '';

    public string $product_search = '';

    public string $sort_by = 'created_at';

    public string $sort_direction = 'desc';

    public function mount(): void
    {
        Gate::authorize('viewAny', InventoryMovement::class);
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedMovementType(): void
    {
        $this->resetPage();
    }

    public function updatedProductSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    public function updatedSortDirection(): void
    {
        $this->resetPage();
    }

    public function applyPresetLast7Days(): void
    {
        $this->date_from = now()->subDays(6)->toDateString();
        $this->date_to = now()->toDateString();
        $this->resetPage();
    }

    public function applyPresetThisMonth(): void
    {
        $this->date_from = now()->startOfMonth()->toDateString();
        $this->date_to = now()->toDateString();
        $this->resetPage();
    }

    public function clearDateFilters(): void
    {
        $this->date_from = '';
        $this->date_to = '';
        $this->resetPage();
    }

    public function isLast7DaysPresetActive(): bool
    {
        if ($this->date_from === '' || $this->date_to === '') {
            return false;
        }

        return $this->date_from === now()->copy()->subDays(6)->toDateString()
            && $this->date_to === now()->toDateString();
    }

    public function isThisMonthPresetActive(): bool
    {
        if ($this->date_from === '' || $this->date_to === '') {
            return false;
        }

        return $this->date_from === now()->copy()->startOfMonth()->toDateString()
            && $this->date_to === now()->toDateString();
    }

    public function isClearDatesPresetActive(): bool
    {
        return $this->date_from === '' && $this->date_to === '';
    }

    /**
     * @return array<string, string|null>
     */
    protected function filterPayload(): array
    {
        return [
            'date_from' => $this->date_from !== '' ? $this->date_from : null,
            'date_to' => $this->date_to !== '' ? $this->date_to : null,
            'movement_type' => $this->movement_type !== '' ? $this->movement_type : null,
            'product_search' => $this->product_search !== '' ? $this->product_search : null,
            'sort' => $this->sort_by,
            'direction' => $this->sort_direction,
        ];
    }

    public function getMovementsProperty(): LengthAwarePaginator
    {
        $tenantId = (int) session('current_tenant_id');

        return app(ListInventoryMovementsForTenantService::class)
            ->query($tenantId, $this->filterPayload())
            ->paginate(10)
            ->setPath(route('inventory.movements.index'));
    }

    public function resolveSource(InventoryMovement $movement): InventoryMovementSourceLink
    {
        return app(ResolveInventoryMovementSourceLinkService::class)->resolve($movement);
    }

    public function exportCsv(ExportInventoryMovementsCsvService $export): StreamedResponse
    {
        Gate::authorize('viewAny', InventoryMovement::class);

        return $export->download((int) session('current_tenant_id'), $this->filterPayload());
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Product Inventory Movements') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Authoritative ledger of stock changes. Operational documents post rows here in a transaction.') }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button wire:click="exportCsv" variant="filled" wire:loading.attr="disabled" class="border border-zinc-400 dark:border-zinc-200">
                <span wire:loading.remove wire:target="exportCsv">{{ __('Export CSV') }}</span>
                <span wire:loading wire:target="exportCsv">{{ __('Exporting…') }}</span>
            </flux:button>
            <flux:button :href="route('inventory.adjustments.create')" variant="primary" wire:navigate>
                {{ __('Record adjustment') }}
            </flux:button>
        </div>
    </div>

    <flux:card class="flex flex-col gap-6 p-6 bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40">
        <div>
            <flux:heading size="lg" class="font-bold!">{{ __('Search and Filter Products') }}</flux:heading>
            <flux:text class="mt-1 text-xs">{{ __('Narrow by Date, Movement Type, or Product Name / SKU.') }}</flux:text>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button type="button" wire:click="applyPresetLast7Days" size="sm" :variant="$this->isLast7DaysPresetActive() ? 'primary' : 'outline'">{{ __('Last 7 days') }}</flux:button>
            <flux:button type="button" wire:click="applyPresetThisMonth" size="sm" :variant="$this->isThisMonthPresetActive() ? 'primary' : 'outline'">{{ __('This month') }}</flux:button>
            <flux:button type="button" wire:click="clearDateFilters" size="sm" :variant="$this->isClearDatesPresetActive() ? 'primary' : 'outline'">{{ __('Clear dates') }}</flux:button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <flux:input wire:model.live.debounce.400ms="date_from" :label="__('From date')" type="date" />
            <flux:input wire:model.live.debounce.400ms="date_to" :label="__('To date')" type="date" />
            <flux:select wire:model.live="movement_type" :label="__('Movement type')">
                <flux:select.option value="">{{ __('All types') }}</flux:select.option>
                @foreach (InventoryMovementType::cases() as $case)
                    <flux:select.option :value="$case->value">{{ ucfirst($case->value) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live.debounce.400ms="product_search" :label="__('Product / SKU')" placeholder="{{ __('Search…') }}" />
            <flux:select wire:model.live="sort_by" :label="__('Sort by')">
                <flux:select.option value="created_at">{{ __('Date / time') }}</flux:select.option>
                <flux:select.option value="quantity">{{ __('Quantity') }}</flux:select.option>
                <flux:select.option value="product">{{ __('Product name') }}</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="sort_direction" :label="__('Direction')">
                <flux:select.option value="desc">{{ __('Descending') }}</flux:select.option>
                <flux:select.option value="asc">{{ __('Ascending') }}</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

    <flux:card class="flex flex-col overflow-hidden p-0">
        <div class="border-b border-zinc-200 px-6 py-5 dark:border-white/10">
            <flux:heading size="lg">{{ __('Movement log') }}</flux:heading>
            <flux:text class="mt-1 text-sm">{{ __('Chronological entries with product, type, signed quantity, source document, and notes.') }}</flux:text>
        </div>

        @if ($this->movements->isEmpty())
            <div class="p-6">
                <flux:callout icon="arrows-right-left" color="zinc" inline :heading="__('No movements match')" :text="__('Try widening filters, record an adjustment, or wait for procurement / sales posting.')" />
            </div>
        @else
            <flux:table>
                <flux:table.columns sticky class="bg-white dark:bg-white/10">
                    <flux:table.column class="px-6!">{{ __('When') }}</flux:table.column>
                    <flux:table.column class="px-6!">{{ __('Product') }}</flux:table.column>
                    <flux:table.column class="px-6!">{{ __('Type') }}</flux:table.column>
                    <flux:table.column align="end" class="px-6!">{{ __('Quantity') }}</flux:table.column>
                    <flux:table.column class="min-w-48 px-6! flex justify-end">{{ __('Source') }}</flux:table.column>
                    <flux:table.column class="px-6!">{{ __('Notes') }}</flux:table.column>
                    <flux:table.column class="px-6!">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->movements as $movement)
                        @php
                            $source = $this->resolveSource($movement);
                        @endphp
                        <flux:table.row :key="$movement->id">
                            <flux:table.cell class="whitespace-nowrap px-6! text-zinc-600 dark:text-zinc-400">
                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">
                                    {{ $movement->created_at->timezone(config('app.timezone'))->translatedFormat('F j, Y') }}
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $movement->created_at->timezone(config('app.timezone'))->translatedFormat('h:i A') }}
                                </p>
                            </flux:table.cell>
                            <flux:table.cell class="px-6!">
                                <div class="font-medium text-zinc-900 dark:text-white text-md!">{{ $movement->product->name }}</div>
                                @if (($movement->product->sku ?? '') !== '')
                                    <div class="text-xs text-zinc-400 dark:text-zinc-400 font-mono tracking-wider font-bold">{{ $movement->product->sku }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="px-6!">
                                <flux:badge :color="$movement->movement_type->fluxBadgeColor()" size="sm" inset="top bottom" class="capitalize">
                                    {{ $movement->movement_type->value }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="end" class="px-6!">
                                <span @class([
                                    'tabular-nums font-medium',
                                    'text-emerald-600 dark:text-emerald-400' => (float) $movement->quantity > 0,
                                    'text-rose-600 dark:text-rose-400' => (float) $movement->quantity < 0,
                                    'text-zinc-700 dark:text-zinc-300' => (float) $movement->quantity == 0.0,
                                ])>{{ \Illuminate\Support\Number::format((float) $movement->quantity, maxPrecision: 4) }}</span>
                            </flux:table.cell>
                            <flux:table.cell class="max-w-md px-6! text-right!">
                                @if ($source->url)
                                    <flux:link :href="$source->url" wire:navigate class="text-sm">{{ $source->label }}</flux:link>
                                @else
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $source->label }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="max-w-xs truncate px-6! text-zinc-600 dark:text-zinc-400">
                                {{ $movement->notes ?? '—' }}
                            </flux:table.cell>
                            <flux:table.cell class="px-6!">
                                <flux:button size="xs" variant="primary" wire:navigate :href="route('products.show', $movement->product->id)">
                                    <flux:icon name="eye" class="w-4 h-4" />
                                    {{ __('View') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    @if (! $this->movements->isEmpty() && $this->movements->hasPages())
        <div class="flex justify-between px-1 sm:px-0 items-center gap-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 w-full">
                {{ __('Showing') }} {{ $this->movements->firstItem() }} {{ __('to') }} {{ $this->movements->lastItem() }} {{ __('of') }} {{ $this->movements->total() }} {{ __('entries') }}
            </flux:text>
            {{ $this->movements->links('vendor.pagination.numbers-only') }}
        </div>
    @endif
</div>
