<?php

use App\Domains\Crm\Services\SearchSuppliersService;
use App\Domains\Inventory\Services\SearchProductsService;
use App\Domains\Procurement\Services\CreateRfqService;
use App\Enums\RfqLineUnitType;
use App\Http\Requests\StoreRfqRequest;
use App\Livewire\Concerns\InteractsWithSearchableSelects;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\Supplier;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Create Request For Quotation'])]
#[Title('Create Request For Quotation')]
class extends Component
{
    use InteractsWithSearchableSelects;

    public string $supplier_id = '';

    public string $supplierSearch = '';

    public string $title = '';

    public string $notes = '';

    /** @var array<int, string> */
    public array $lineProductSearch = [];

    /** @var list<array{product_id: string, quantity: string, unit_type: string, unit_price: string, notes: string}> */
    public array $lines = [];

    public function mount(): void
    {
        Gate::authorize('create', Rfq::class);
        $this->lines = [
            ['product_id' => '', 'quantity' => '', 'unit_type' => RfqLineUnitType::Piece->value, 'unit_price' => '', 'notes' => ''],
        ];

        $prefill = request()->query('supplier_id');
        if ($prefill !== null && $prefill !== '') {
            $tenantId = (int) session('current_tenant_id');
            $exists = Supplier::query()
                ->where('tenant_id', $tenantId)
                ->whereKey((int) $prefill)
                ->exists();
            if ($exists) {
                $supplier = Supplier::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey((int) $prefill)
                    ->first(['id', 'name']);
                if ($supplier !== null) {
                    $this->supplier_id = (string) $supplier->id;
                    $this->supplierSearch = $supplier->name;
                }
            }
        }
    }

    public function addLine(): void
    {
        $this->lines[] = ['product_id' => '', 'quantity' => '', 'unit_type' => RfqLineUnitType::Piece->value, 'unit_price' => '', 'notes' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        if ($this->lines === []) {
            $this->lines = [['product_id' => '', 'quantity' => '', 'unit_type' => RfqLineUnitType::Piece->value, 'unit_price' => '', 'notes' => '']];
        }
    }

    public function save(CreateRfqService $service): void
    {
        Gate::authorize('create', Rfq::class);

        $validated = $this->validate((new StoreRfqRequest)->rules());

        $userId = auth()->id();
        if ($userId === null) {
            return;
        }

        $service->execute((int) session('current_tenant_id'), $validated, (int) $userId);

        Flux::toast(variant: 'success', text: __('RFQ created.'));

        $this->redirect(route('procurement.rfqs.index', absolute: false), navigate: true);
    }

    public function getSupplierSearchResultsProperty(): Collection
    {
        $tenantId = (int) session('current_tenant_id');

        return app(SearchSuppliersService::class)->execute(
            $tenantId,
            $this->supplierSearch,
            $this->supplier_id !== '' ? (int) $this->supplier_id : null,
        );
    }

    public function getSelectedSupplierProperty(): ?Supplier
    {
        if ($this->supplier_id === '') {
            return null;
        }

        return Supplier::query()
            ->where('tenant_id', (int) session('current_tenant_id'))
            ->whereKey((int) $this->supplier_id)
            ->first(['id', 'name']);
    }

    public function productSearchResultsForLine(int $index): Collection
    {
        $tenantId = (int) session('current_tenant_id');
        $term = $this->lineProductSearch[$index] ?? '';
        $selectedId = $this->lines[$index]['product_id'] ?? '';

        return app(SearchProductsService::class)->execute(
            $tenantId,
            $term,
            $selectedId !== '' ? (int) $selectedId : null,
        );
    }

    public function productLabelForLine(int $index): string
    {
        $line = $this->lines[$index] ?? null;
        if (! is_array($line)) {
            return __('Line :num', ['num' => $index + 1]);
        }

        $pid = $line['product_id'] ?? '';
        if ($pid === '') {
            return __('No product selected');
        }

        $product = Product::query()
            ->where('tenant_id', (int) session('current_tenant_id'))
            ->whereKey((int) $pid)
            ->first(['name']);

        return $product !== null ? $product->name : __('Unknown product');
    }

    public function lineSubtotalFormatted(int $index): string
    {
        $line = $this->lines[$index] ?? null;
        if (! is_array($line)) {
            return '—';
        }

        $q = (float) ($line['quantity'] ?? 0);
        $rawPrice = $line['unit_price'] ?? '';
        $unitPrice = $rawPrice === '' ? 0.0 : (float) $rawPrice;
        if ($q <= 0) {
            return '—';
        }

        return number_format($q * $unitPrice, 2);
    }

    public function rfqGrandTotalFormatted(): string
    {
        $sum = 0.0;
        foreach ($this->lines as $line) {
            $q = (float) ($line['quantity'] ?? 0);
            $rawPrice = $line['unit_price'] ?? '';
            $unitPrice = $rawPrice === '' ? 0.0 : (float) $rawPrice;
            if ($q > 0) {
                $sum += $q * $unitPrice;
            }
        }

        return number_format($sum, 2);
    }

    public function lineQuantityTimesUnitPriceCaption(int $index): string
    {
        $line = $this->lines[$index] ?? null;
        if (! is_array($line)) {
            return '—';
        }

        $rawQty = $line['quantity'] ?? '';
        $q = $rawQty === '' ? 0.0 : (float) $rawQty;
        $rawPrice = $line['unit_price'] ?? '';

        if ($q <= 0) {
            return '—';
        }

        if ($rawPrice !== '') {
            return $rawQty.' × '.number_format((float) $rawPrice, 2);
        }

        return __('Qty: :qty', ['qty' => $rawQty]);
    }
}; ?>

<div class="flex flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Purchase Request For Quotation') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Purchase Request For Quotation is a document that requests pricing from a supplier for one or more products.') }}</flux:text>
    </div>
    <div class="flex flex-1 flex-col gap-8 lg:flex-row lg:items-start lg:gap-8">
        <form wire:submit="save" class="flex gap-6 px-6 py-6">
            <div class="flex min-w-0 max-w-2/3 flex-1 flex-col gap-8">
                <flux:card class="flex flex-col overflow-hidden p-0 bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40">
                    <div class="border-b border-zinc-200 px-6 py-5 dark:border-white/10">
                        <flux:heading size="lg">{{ __('Purchase Request Details') }}</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ __('Fill in supplier details and product lines to prepare an RFQ.') }}</flux:text>
                    </div>

                    <div class="flex flex-col gap-6 px-6 py-6">
                        <x-searchable-select
                            wire:model="supplier_id"
                            search-wire="supplierSearch"
                            value-property="supplier_id"
                            search-property="supplierSearch"
                            :label="__('Supplier')"
                            :placeholder="__('Search suppliers…')"
                            display-format="supplier"
                            :results="$this->supplierSearchResults"
                            required
                        />

                        <flux:input wire:model.live="title" :label="__('Title')" type="text" :placeholder="__('Optional short label')" />

                        <flux:textarea wire:model.live="notes" :label="__('Notes')" rows="2" />

                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <flux:heading size="lg">{{ __('Product Items') }}</flux:heading>
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-300">{{ __('Optional. Can be updated from the purchase order when goods are received.') }}</flux:text>
                                </div>
                                <flux:button type="button" wire:click="addLine" variant="ghost" size="sm" class="cursor-pointer border border-zinc-200 bg-zinc-100 p-2 dark:border-zinc-600 dark:bg-zinc-700">
                                    {{ __('Add Product') }}
                                </flux:button>
                            </div>

                            @foreach ($lines as $index => $line)
                                <div wire:key="rfq-line-{{ $index }}" class="grid gap-4 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800 md:grid-cols-12 md:items-end">
                                    <div class="md:col-span-4">
                                        <x-searchable-select
                                            wire:model="lines.{{ $index }}.product_id"
                                            :search-wire="'lineProductSearch.'.$index"
                                            :value-property="'lines.'.$index.'.product_id'"
                                            :search-property="'lineProductSearch.'.$index"
                                            :label="__('Product')"
                                            :placeholder="__('Search products…')"
                                            display-format="name_sku"
                                            :results="$this->productSearchResultsForLine($index)"
                                            required
                                        />
                                    </div>
                                    <div class="md:col-span-1">
                                        <flux:input wire:model.live="lines.{{ $index }}.quantity" :label="__('Qty')" type="text" inputmode="decimal" required />
                                    </div>
                                    <div class="md:col-span-2">
                                        <flux:select wire:model="lines.{{ $index }}.unit_type" :label="__('Unit type')" required>
                                            @foreach (RfqLineUnitType::cases() as $unitType)
                                                <flux:select.option :value="$unitType->value">{{ $unitType->label() }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </div>
                                    <div class="md:col-span-2">
                                        <flux:input wire:model.live="lines.{{ $index }}.unit_price" :label="__('Unit Price')" type="text" inputmode="decimal" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <flux:input wire:model="lines.{{ $index }}.notes" :label="__('Notes')" type="text" />
                                    </div>
                                    <div class="md:col-span-1 flex justify-end pb-3">
                                        <flux:button type="button" wire:click="removeLine({{ $index }})" class="cursor-pointer dark:text-zinc-100" variant="ghost" size="xs">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                            </svg>
                                        </flux:button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </flux:card>
            </div>

            @if($this->rfqGrandTotalFormatted() > 0)
                <aside class="w-full shrink-0 lg:sticky lg:top-6 lg:w-80 xl:w-96">
                    <flux:card class="flex flex-col gap-4 border border-zinc-300 bg-neutral-100 p-6 dark:border-zinc-300/40 dark:bg-neutral-700">
                        <div>
                            <flux:heading size="xl">{{ __('Purchase Request Summary') }}</flux:heading>
                            {{-- <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Line subtotals from quantity × unit price.') }}</flux:text> --}}
                        </div>

                        <div class="flex flex-col gap-4">
                            <div>
                                <flux:text class="font-medium leading-snug text-zinc-900 dark:text-zinc-100">{{ __('Supplier') }}</flux:text>
                                <flux:text class="mt-0.5 text-lg text-zinc-600 dark:text-zinc-300">{{ $this->selectedSupplier?->name ?? __('No supplier selected') }}</flux:text>
                            </div>

                            @if($title !== '')
                                <div>
                                    <flux:text class="font-medium leading-snug text-zinc-900 dark:text-zinc-100">{{ __('Title') }}</flux:text>
                                    <flux:text class="mt-0.5 text-sm text-zinc-600 dark:text-zinc-300">{{ $title !== '' ? $title : '—' }}</flux:text>
                                </div>
                            @endif

                            @if (trim($notes) !== '')
                                <div>
                                    <flux:text class="font-medium leading-snug text-zinc-900 dark:text-zinc-100">{{ __('Notes') }}</flux:text>
                                    <flux:text class="mt-0.5 whitespace-pre-wrap text-sm text-zinc-600 dark:text-zinc-300">{{ $notes }}</flux:text>
                                </div>
                            @endif
                        </div>

                        <div class="divide-y divide-zinc-200 dark:divide-zinc-600">
                            <flux:heading size="sm">{{ __('Product Items') }}</flux:heading>
                            @foreach ($lines as $index => $line)
                                <div wire:key="rfq-summary-{{ $index }}" class="flex gap-3 py-3 first:pt-0">
                                    <div class="min-w-0 flex-1">
                                        <flux:text class="font-medium leading-snug text-zinc-900 dark:text-zinc-100">{{ $this->productLabelForLine($index) }}</flux:text>
                                        <flux:text class="mt-0.5 text-xs tabular-nums text-zinc-500 dark:text-zinc-400">{{ $this->lineQuantityTimesUnitPriceCaption($index) }}</flux:text>
                                    </div>
                                    <flux:text class="shrink-0 tabular-nums font-medium text-zinc-900 dark:text-zinc-100">{{ $this->lineSubtotalFormatted($index) }}</flux:text>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex flex-col gap-1 border-t border-zinc-200 pt-4 dark:border-zinc-600 sm:flex-row sm:items-center sm:justify-between">
                            <flux:text class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ __('Grand total') }}</flux:text>
                            <flux:text class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->rfqGrandTotalFormatted() }}</flux:text>
                        </div>
                    </flux:card>
                    <div class="flex flex-wrap gap-3 mt-4">
                        <flux:button variant="primary" type="submit">{{ __('Create Request For Quotation') }}</flux:button>
                        <flux:button :href="route('procurement.rfqs.index')" class="cursor-pointer border border-zinc-200 dark:border-zinc-700 dark:bg-zinc-700 dark:text-zinc-100" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
                    </div>
                </aside>
            @endif
        </form>
    </div>
</div>
