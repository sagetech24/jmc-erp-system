<?php

use App\Domains\Inventory\Services\SearchProductsService;
use App\Domains\Procurement\Services\DeleteRfqService;
use App\Domains\Procurement\Services\UpdateRfqService;
use App\Enums\RfqLineUnitType;
use App\Enums\RfqStatus;
use App\Http\Requests\UpdateRfqRequest;
use App\Livewire\Concerns\InteractsWithSearchableSelects;
use App\Models\Rfq;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Edit Request For Quotation'])]
#[Title('Edit Request For Quotation')]
class extends Component {
    use InteractsWithSearchableSelects;

    public Rfq $rfq;

    public string $title = '';

    public string $notes = '';

    /** @var array<int, string> */
    public array $lineProductSearch = [];

    /** @var list<array{product_id: string, quantity: string, unit_type: string, unit_price: string, notes: string}> */
    public array $lines = [];

    public function mount(int $id): void
    {
        $tenantId = (int) session('current_tenant_id');
        $this->rfq = Rfq::query()
            ->where('tenant_id', $tenantId)
            ->with(['lines.product', 'supplier'])
            ->findOrFail($id);

        Gate::authorize('update', $this->rfq);

        if ($this->rfq->status === RfqStatus::Closed || $this->rfq->purchaseOrders()->exists()) {
            $this->redirect(route('procurement.rfqs.show', $this->rfq, absolute: false), navigate: true);

            return;
        }

        if (! in_array($this->rfq->status, [RfqStatus::PendingForApproval, RfqStatus::ApprovedNoPo], true)) {
            $this->redirect(route('procurement.rfqs.show', $this->rfq, absolute: false), navigate: true);

            return;
        }

        $this->title = $this->rfq->title ?? '';
        $this->notes = $this->rfq->notes ?? '';

        $this->lines = [];
        foreach ($this->rfq->lines as $index => $line) {
            $this->lines[] = [
                'product_id' => (string) $line->product_id,
                'quantity' => (string) $line->quantity,
                'unit_type' => $line->unit_type->value,
                'unit_price' => $line->unit_price !== null ? (string) $line->unit_price : '',
                'notes' => $line->notes ?? '',
            ];
            $this->lineProductSearch[$index] = $line->product?->name ?? '';
        }
        if ($this->lines === []) {
            $this->lines = [['product_id' => '', 'quantity' => '', 'unit_type' => RfqLineUnitType::Piece->value, 'unit_price' => '', 'notes' => '']];
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

    public function save(UpdateRfqService $service): void
    {
        Gate::authorize('update', $this->rfq);

        $validated = $this->validate((new UpdateRfqRequest)->rules());

        try {
            $service->execute($this->rfq, $validated);
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('RFQ updated.'));

        $this->redirect(route('procurement.rfqs.show', $this->rfq, absolute: false), navigate: true);
    }

    public function deleteRfq(DeleteRfqService $service): void
    {
        Gate::authorize('delete', $this->rfq);

        try {
            $service->execute($this->rfq);
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('RFQ deleted.'));

        $this->redirect(route('procurement.rfqs.index', absolute: false), navigate: true);
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
}; ?>

<div class="flex max-w-2/3 flex-1 flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Edit Request For Quotation') }}</flux:heading>
        <flux:text class="mt-1">{{ __(':code — Update notes and product lines. The supplier cannot be changed.', ['code' => $rfq->reference_code]) }}</flux:text>
    </div>

    <flux:card class="flex flex-col overflow-hidden p-0 bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40">
        <div class="border-b border-zinc-200 px-6 py-5 dark:border-white/10">
            <flux:heading size="lg">{{ __('Request For Quotation Details') }}</flux:heading>
            <flux:text class="mt-1 text-sm">{{ __('Update title, notes, and product lines for this RFQ.') }}</flux:text>
        </div>

        <form wire:submit="save" class="flex flex-col gap-6 px-6 py-6">
            <flux:input
                :value="$rfq->supplier->name"
                :label="__('Supplier')"
                disabled
                readonly
            />

            <flux:input wire:model="title" :label="__('Title')" type="text" :placeholder="__('Optional short label')" />

            <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />

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
                    <div wire:key="rfq-edit-line-{{ $index }}" class="grid gap-4 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800 md:grid-cols-12 md:items-end">
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
                            <flux:input wire:model="lines.{{ $index }}.quantity" :label="__('Qty')" type="text" inputmode="decimal" required />
                        </div>
                        <div class="md:col-span-2">
                            <flux:select wire:model="lines.{{ $index }}.unit_type" :label="__('Unit type')" required>
                                @foreach (RfqLineUnitType::cases() as $unitType)
                                    <flux:select.option :value="$unitType->value">{{ $unitType->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="md:col-span-2">
                            <flux:input wire:model="lines.{{ $index }}.unit_price" :label="__('Unit Price')" type="text" inputmode="decimal" />
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

            <div class="flex flex-wrap gap-3">
                <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
                <flux:button :href="route('procurement.rfqs.show', $rfq)" class="cursor-pointer border border-zinc-200 dark:border-zinc-700 dark:bg-zinc-700 dark:text-zinc-100" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>

        @can('delete', $rfq)
            <div class="border-t border-zinc-200 px-6 py-6 dark:border-white/10">
                <div class="space-y-2">
                    <flux:heading size="lg" class="text-red-600 dark:text-red-400">{{ __('Delete this RFQ') }}</flux:heading>
                    <flux:text class="text-sm">
                        {{ __('Permanently remove this request and all of its line items. This cannot be undone.') }}
                    </flux:text>
                </div>
                <div class="mt-4">
                    <flux:modal.trigger name="delete-rfq">
                        <flux:button variant="danger" type="button">{{ __('Delete this RFQ') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            <flux:modal name="delete-rfq" class="max-w-lg">
                <div class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('Delete this RFQ?') }}</flux:heading>
                        <flux:text class="mt-1">
                            {{ __(':code will be removed along with all product lines.', ['code' => $rfq->reference_code]) }}
                        </flux:text>
                    </div>
                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button variant="danger" type="button" wire:click="deleteRfq">{{ __('Delete') }}</flux:button>
                    </div>
                </div>
            </flux:modal>
        @endcan
    </flux:card>
</div>
