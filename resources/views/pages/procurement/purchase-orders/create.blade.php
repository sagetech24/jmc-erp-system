<?php

use App\Domains\Crm\Services\SearchSuppliersService;
use App\Domains\Inventory\Services\SearchProductsService;
use App\Domains\Procurement\Services\CreatePurchaseOrderService;
use App\Domains\Procurement\Validation\PurchaseOrderStoreRules;
use App\Enums\RfqStatus;
use App\Livewire\Concerns\InteractsWithSearchableSelects;
use App\Models\Rfq;
use App\Models\Supplier;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'New purchase order'])]
#[Title('New purchase order')]
class extends Component {
    use InteractsWithSearchableSelects;

    public string $rfq_id = '';

    public string $supplier_id = '';

    public string $supplierSearch = '';

    public string $order_date = '';

    public string $notes = '';

    /** @var array<int, string> */
    public array $lineProductSearch = [];

    /** @var list<array{product_id: string, quantity_ordered: string, unit_cost: string, rfq_line_id: string}> */
    public array $lines = [];

    public function mount(): void
    {
        Gate::authorize('create', \App\Models\PurchaseOrder::class);
        $this->order_date = now()->toDateString();
        $this->lines = [
            ['product_id' => '', 'quantity_ordered' => '', 'unit_cost' => '', 'rfq_line_id' => ''],
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

    public function updatedRfqId(string $value): void
    {
        if ($value === '') {
            $this->supplier_id = '';
            $this->supplierSearch = '';
            $this->lineProductSearch = [];
            $this->notes = '';
            $this->lines = [
                ['product_id' => '', 'quantity_ordered' => '', 'unit_cost' => '', 'rfq_line_id' => ''],
            ];

            return;
        }

        $tenantId = (int) session('current_tenant_id');
        $rfq = Rfq::query()
            ->where('tenant_id', $tenantId)
            ->whereDoesntHave('purchaseOrders')
            ->whereNot('status', RfqStatus::PendingForApproval)
            ->whereNot('status', RfqStatus::Closed)
            ->has('lines')
            ->with(['lines.product', 'supplier'])
            ->find((int) $value);

        if ($rfq === null) {
            Flux::toast(variant: 'danger', text: __('That RFQ is not available for a new purchase order.'));
            $this->rfq_id = '';

            return;
        }

        Gate::authorize('view', $rfq);

        $this->supplier_id = (string) $rfq->supplier_id;
        $this->supplierSearch = $rfq->supplier?->name ?? '';
        $this->notes = $rfq->notes ?? '';
        $this->lines = [];
        $this->lineProductSearch = [];
        foreach ($rfq->lines as $index => $line) {
            $this->lines[] = [
                'product_id' => (string) $line->product_id,
                'quantity_ordered' => (string) $line->quantity,
                'unit_cost' => $line->unit_price !== null ? (string) $line->unit_price : '',
                'rfq_line_id' => (string) $line->id,
            ];
            $this->lineProductSearch[$index] = $line->product?->name ?? '';
        }
    }

    public function clearRfqTemplate(): void
    {
        $this->rfq_id = '';
        $this->supplier_id = '';
        $this->supplierSearch = '';
        $this->lineProductSearch = [];
        $this->notes = '';
        $this->lines = [
            ['product_id' => '', 'quantity_ordered' => '', 'unit_cost' => '', 'rfq_line_id' => ''],
        ];
    }

    public function addLine(): void
    {
        if ($this->rfq_id !== '') {
            Flux::toast(variant: 'warning', text: __('Clear the RFQ import to add or remove lines manually.'));

            return;
        }

        $this->lines[] = ['product_id' => '', 'quantity_ordered' => '', 'unit_cost' => '', 'rfq_line_id' => ''];
    }

    public function removeLine(int $index): void
    {
        if ($this->rfq_id !== '') {
            Flux::toast(variant: 'warning', text: __('Clear the RFQ import to add or remove lines manually.'));

            return;
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        if ($this->lines === []) {
            $this->lines = [['product_id' => '', 'quantity_ordered' => '', 'unit_cost' => '', 'rfq_line_id' => '']];
        }
    }

    /**
     * @return array{supplier_id: int, rfq_id: int|null, order_date: string, notes: string|null, lines: list<array{product_id: int, quantity_ordered: string, unit_cost: string|null, rfq_line_id: int|null}>}
     */
    private function purchaseOrderPayloadForValidation(): array
    {
        return [
            'supplier_id' => (int) $this->supplier_id,
            'rfq_id' => $this->rfq_id === '' ? null : (int) $this->rfq_id,
            'order_date' => $this->order_date,
            'notes' => $this->notes === '' ? null : $this->notes,
            'lines' => array_values(array_map(
                function (array $line): array {
                    $uc = $line['unit_cost'] ?? '';

                    return [
                        'product_id' => (int) ($line['product_id'] ?? 0),
                        'quantity_ordered' => (string) ($line['quantity_ordered'] ?? ''),
                        'unit_cost' => $uc === '' ? null : $uc,
                        'rfq_line_id' => empty($line['rfq_line_id'] ?? '') ? null : (int) $line['rfq_line_id'],
                    ];
                },
                $this->lines,
            )),
        ];
    }

    public function lineTotalFormatted(int $index): string
    {
        $line = $this->lines[$index] ?? null;
        if (! is_array($line)) {
            return '—';
        }

        $q = (float) ($line['quantity_ordered'] ?? 0);
        $c = (float) ($line['unit_cost'] ?? 0);
        if ($q <= 0) {
            return '—';
        }

        return number_format($q * $c, 2);
    }

    public function orderTotalFormatted(): string
    {
        $sum = 0.0;
        foreach ($this->lines as $line) {
            $q = (float) ($line['quantity_ordered'] ?? 0);
            $c = (float) ($line['unit_cost'] ?? 0);
            $sum += $q * $c;
        }

        return number_format($sum, 2);
    }

    public function save(CreatePurchaseOrderService $service): void
    {
        Gate::authorize('create', \App\Models\PurchaseOrder::class);

        $tenantId = (int) session('current_tenant_id');
        $payload = $this->purchaseOrderPayloadForValidation();

        $validator = Validator::make($payload, PurchaseOrderStoreRules::rules($tenantId));
        PurchaseOrderStoreRules::withValidatorAfter($validator, $tenantId);

        $validated = $validator->validate();

        try {
            $po = $service->execute($tenantId, $validated);
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Purchase order created.'));

        $this->redirect(route('procurement.purchase-orders.show', $po, absolute: false), navigate: true);
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

    public function getEligibleRfqsProperty()
    {
        return Rfq::query()
            ->where('tenant_id', (int) session('current_tenant_id'))
            ->whereDoesntHave('purchaseOrders')
            ->whereNot('status', RfqStatus::PendingForApproval)
            ->whereNot('status', RfqStatus::Closed)
            ->has('lines')
            ->with('supplier')
            ->orderByDesc('id')
            ->get();
    }
}; ?>

<div class="flex max-w-2/3 flex-1 flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('New purchase order') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Commit quantities and costs. Receiving posts inventory movements against these lines.') }}</flux:text>
    </div>

    <flux:card class="flex flex-col overflow-hidden border border-zinc-300 bg-neutral-100 p-0 dark:border-zinc-300/40 dark:bg-neutral-700">
        <div class="border-b border-zinc-200 px-6 py-5 dark:border-white/10">
            <flux:heading size="lg">{{ __('Purchase order details') }}</flux:heading>
            <flux:text class="mt-1 text-sm">{{ __('Choose a supplier and line items. Optionally start from an approved RFQ to carry over pricing links.') }}</flux:text>
        </div>

        <form wire:submit="save" class="flex flex-col gap-6 px-6 py-6">
            @if ($this->eligibleRfqs->isNotEmpty())
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div class="min-w-0 flex-1 space-y-1">
                        <flux:select wire:model.live="rfq_id" :label="__('Start from RFQ (optional)')" :placeholder="__('None — manual entry')">
                            <flux:select.option value="">{{ __('None — manual entry') }}</flux:select.option>
                            @foreach ($this->eligibleRfqs as $rfq)
                                <flux:select.option :value="$rfq->id">
                                    {{ $rfq->reference_code }}
                                    @if ($rfq->title)
                                        — {{ $rfq->title }}
                                    @endif
                                    @if ($rfq->supplier)
                                        · {{ $rfq->supplier->name }}
                                    @endif
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-300">{{ __('Imports supplier, notes, and lines. You may adjust quantities and unit costs before saving.') }}</flux:text>
                    </div>
                    @if ($rfq_id !== '')
                        <flux:button type="button" wire:click="clearRfqTemplate" variant="ghost" size="sm" class="shrink-0 cursor-pointer border border-zinc-200 bg-zinc-100 p-2 dark:border-zinc-600 dark:bg-zinc-700">
                            {{ __('Clear RFQ import') }}
                        </flux:button>
                    @endif
                </div>
            @endif

            <x-searchable-select
                wire:model="supplier_id"
                search-wire="supplierSearch"
                value-property="supplier_id"
                search-property="supplierSearch"
                :label="__('Supplier')"
                :placeholder="__('Search suppliers…')"
                display-format="supplier"
                :results="$this->supplierSearchResults"
                :disabled="$rfq_id !== ''"
                required
            />

            <flux:input wire:model="order_date" :label="__('Order date')" type="date" required />

            <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />

            <div class="space-y-4">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ __('Product lines') }}</flux:heading>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-300">{{ __('Line totals update as you enter quantity and unit cost.') }}</flux:text>
                    </div>
                    <flux:button type="button" wire:click="addLine" variant="ghost" size="sm" class="cursor-pointer shrink-0 border border-zinc-200 bg-zinc-100 p-2 dark:border-zinc-600 dark:bg-zinc-700">
                        {{ __('Add product') }}
                    </flux:button>
                </div>

                @foreach ($lines as $index => $line)
                    <div wire:key="po-line-{{ $index }}" class="grid gap-4 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800 md:grid-cols-12 md:items-end">
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
                                :disabled="$rfq_id !== ''"
                                required
                            />
                        </div>
                        <div class="md:col-span-2">
                            <flux:input wire:model.live="lines.{{ $index }}.quantity_ordered" :label="__('Qty ordered')" type="text" inputmode="decimal" required />
                        </div>
                        <div class="md:col-span-2">
                            <flux:input wire:model.live="lines.{{ $index }}.unit_cost" :label="__('Unit cost')" type="text" inputmode="decimal" />
                        </div>
                        <div class="md:col-span-2">
                            <flux:text class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Line total') }}</flux:text>
                            <flux:text class="mt-1 text-sm font-medium tabular-nums text-zinc-900 dark:text-zinc-100">{{ $this->lineTotalFormatted($index) }}</flux:text>
                        </div>
                        <div class="md:col-span-1 flex justify-end pb-3">
                            <flux:button type="button" wire:click="removeLine({{ $index }})" class="cursor-pointer dark:text-zinc-100" variant="ghost" size="xs" :disabled="$rfq_id !== ''">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-col gap-2 rounded-lg border border-dashed border-zinc-300 bg-white/60 px-4 py-3 dark:border-zinc-600 dark:bg-zinc-800/60 sm:flex-row sm:items-center sm:justify-between">
                <flux:text class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Order total (excl. tax)') }}</flux:text>
                <flux:text class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->orderTotalFormatted() }}</flux:text>
            </div>

            <div class="flex flex-wrap gap-3">
                <flux:button variant="primary" type="submit">{{ __('Save purchase order') }}</flux:button>
                <flux:button :href="route('procurement.purchase-orders.index')" class="cursor-pointer border border-zinc-200 dark:border-zinc-700 dark:bg-zinc-700 dark:text-zinc-100" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
