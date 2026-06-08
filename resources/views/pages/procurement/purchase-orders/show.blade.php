<?php

use App\Domains\Accounting\Services\ClearSupplierAdvancePdcService;
use App\Domains\Accounting\Services\RecordSupplierAdvanceService;
use App\Domains\Accounting\Services\RecordSupplierPaymentService;
use App\Domains\Accounting\Support\OpenItemStatusResolver;
use App\Domains\Procurement\Services\ClosePurchaseOrderService;
use App\Domains\Procurement\Support\PurchaseOrderTotalCalculator;
use App\Enums\AccountingOpenItemStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierAdvanceStatus;
use App\Enums\SupplierPaymentMethod;
use App\Http\Requests\ClosePurchaseOrderRequest;
use App\Http\Requests\StoreSupplierAdvanceRequest;
use App\Http\Requests\StoreSupplierPaymentRequest;
use App\Models\AccountsPayable;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\SupplierAdvance;
use App\Models\SupplierPayment;
use App\Support\TenantMoney;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Purchase order'])]
#[Title('Purchase order')]
class extends Component {
    public PurchaseOrder $purchaseOrder;

    public string $amount = '';

    public string $payment_method = '';

    public string $paid_at = '';

    public string $reference = '';

    public string $notes = '';

    /** @var array<int|string, string> */
    public array $allocationAmounts = [];

    public string $close_reason = '';

    public string $advance_amount = '';

    public string $advance_payment_method = '';

    public string $advance_paid_at = '';

    public string $advance_reference = '';

    public string $advance_notes = '';

    public string $advance_cheque_number = '';

    public string $advance_cheque_date = '';

    public string $advance_cheque_bank = '';

    public string $advance_cheque_payee = '';

    public function mount(int $id): void
    {
        $tenantId = (int) session('current_tenant_id');
        $this->purchaseOrder = $this->loadPurchaseOrder($tenantId, $id);

        Gate::authorize('view', $this->purchaseOrder);

        $this->payment_method = SupplierPaymentMethod::Cash->value;
        $this->paid_at = Carbon::now()->format('Y-m-d\TH:i');
        $this->advance_payment_method = SupplierPaymentMethod::Cash->value;
        $this->advance_paid_at = Carbon::now()->format('Y-m-d\TH:i');
    }

    private function loadPurchaseOrder(int $tenantId, int $id): PurchaseOrder
    {
        return PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'supplier',
                'lines.product',
                'goodsReceipts.lines.purchaseOrderLine.product',
                'goodsReceipts.accountsPayable',
                'supplierAdvances.recordedByUser',
                'supplierAdvances.applications',
                'closedByUser',
            ])
            ->findOrFail($id);
    }

    private function refreshPurchaseOrder(): void
    {
        $this->purchaseOrder = $this->loadPurchaseOrder(
            (int) session('current_tenant_id'),
            (int) $this->purchaseOrder->id,
        );
    }

    public function getOrderTotalProperty(): string
    {
        return PurchaseOrderTotalCalculator::calculate($this->purchaseOrder);
    }

    public function getAdvanceSummaryProperty(): array
    {
        $total = '0';
        $applied = '0';
        $unapplied = '0';
        $scheduled = 0;

        foreach ($this->purchaseOrder->supplierAdvances as $advance) {
            if ($advance->status === SupplierAdvanceStatus::Cancelled) {
                continue;
            }

            $total = bcadd($total, (string) $advance->amount, 4);
            $applied = bcadd($applied, (string) $advance->amount_applied, 4);

            if ($advance->status === SupplierAdvanceStatus::Scheduled) {
                $scheduled++;
            } elseif (bccomp($advance->remainingAmount(), '0', 4) === 1) {
                $unapplied = bcadd($unapplied, $advance->remainingAmount(), 4);
            }
        }

        return [
            'total' => $total,
            'applied' => $applied,
            'unapplied' => $unapplied,
            'scheduled_count' => $scheduled,
        ];
    }

    public function canRecordAdvance(): bool
    {
        return in_array($this->purchaseOrder->status, [PurchaseOrderStatus::Confirmed, PurchaseOrderStatus::PartiallyReceived], true);
    }

    public function preparePaymentModal(): void
    {
        Gate::authorize('create', SupplierPayment::class);
        $this->allocationAmounts = [];
        $this->amount = '';
        $this->reference = '';
        $this->notes = '';
        $this->payment_method = SupplierPaymentMethod::Cash->value;
        $this->paid_at = Carbon::now()->format('Y-m-d\TH:i');
    }

    public function prepareAdvanceModal(): void
    {
        Gate::authorize('create', SupplierAdvance::class);
        $this->advance_amount = '';
        $this->advance_reference = '';
        $this->advance_notes = '';
        $this->advance_payment_method = SupplierPaymentMethod::Cash->value;
        $this->advance_paid_at = Carbon::now()->format('Y-m-d\TH:i');
        $this->resetAdvancePaymentMethodFields();
    }

    public function updatedAdvancePaymentMethod(): void
    {
        $this->resetAdvancePaymentMethodFields();
    }

    private function resetAdvancePaymentMethodFields(): void
    {
        $this->advance_cheque_number = '';
        $this->advance_cheque_date = '';
        $this->advance_cheque_bank = '';
        $this->advance_cheque_payee = '';
    }

    /**
     * @return array<string, mixed>
     */
    private function advancePaymentMethodDetailRules(): array
    {
        return match ($this->advance_payment_method) {
            SupplierPaymentMethod::Pdc->value => [
                'cheque_number' => ['required', 'string', 'max:64'],
                'cheque_date' => ['required', 'date'],
                'cheque_bank' => ['required', 'string', 'max:255'],
                'cheque_payee' => ['nullable', 'string', 'max:255'],
            ],
            default => [],
        };
    }

    private function resolveAdvanceReference(): ?string
    {
        if ($this->advance_reference !== '') {
            return $this->advance_reference;
        }

        return $this->advance_payment_method === SupplierPaymentMethod::Pdc->value && $this->advance_cheque_number !== ''
            ? $this->advance_cheque_number
            : null;
    }

    public function getOpenPayablesForPurchaseOrderProperty()
    {
        $tenantId = (int) session('current_tenant_id');
        $grIds = $this->purchaseOrder->goodsReceipts->pluck('id');
        if ($grIds->isEmpty()) {
            return collect();
        }

        return AccountsPayable::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('goods_receipt_id', $grIds)
            ->whereIn('status', [AccountingOpenItemStatus::Open, AccountingOpenItemStatus::Partial])
            ->orderBy('posted_at')
            ->get();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getLineMetricsProperty(): Collection
    {
        return $this->purchaseOrder->lines->map(function ($line): array {
            $received = $line->totalReceivedQuantity();
            $ordered = (string) $line->quantity_ordered;
            $remaining = bcsub($ordered, $received, 4);

            return [
                'line' => $line,
                'ordered' => $ordered,
                'received' => $received,
                'remaining' => $remaining,
                'completion_percentage' => bccomp($ordered, '0', 4) === 1
                    ? max(0.0, min(100.0, ((float) $received / (float) $ordered) * 100))
                    : 0.0,
            ];
        });
    }

    /**
     * Weighted-average unit cost for received quantities on this receipt (value ÷ qty).
     * When accounts payable exists, value matches posted liability ÷ qty; otherwise the
     * same line rules as PostAccountsPayableFromGoodsReceiptService (line unit_cost, else PO line).
     */
    public function receiptWeightedAverageUnitCost(GoodsReceipt $receipt): string
    {
        $receiptQty = $receipt->lines->reduce(
            fn (string $carry, $line) => bcadd($carry, (string) $line->quantity_received, 4),
            '0'
        );
        if (bccomp($receiptQty, '0', 4) !== 1) {
            return '0';
        }

        $payable = $receipt->accountsPayable;
        if ($payable !== null) {
            return bcdiv((string) $payable->total_amount, $receiptQty, 4);
        }

        $totalValue = '0';
        foreach ($receipt->lines as $line) {
            $qty = (string) $line->quantity_received;
            $poLine = $line->purchaseOrderLine;
            $unitCost = $line->unit_cost !== null
                ? (string) $line->unit_cost
                : ($poLine !== null && $poLine->unit_cost !== null ? (string) $poLine->unit_cost : '0');
            $totalValue = bcadd($totalValue, bcmul($qty, $unitCost, 4), 4);
        }

        return bcdiv($totalValue, $receiptQty, 4);
    }

    public function prepareCloseModal(): void
    {
        Gate::authorize('update', $this->purchaseOrder);
        $this->close_reason = '';
    }

    public function closePurchaseOrder(ClosePurchaseOrderService $service): void
    {
        Gate::authorize('update', $this->purchaseOrder);

        if (! in_array($this->purchaseOrder->status, [PurchaseOrderStatus::Confirmed, PurchaseOrderStatus::PartiallyReceived], true)) {
            Flux::toast(variant: 'danger', text: __('Only open purchase orders can be closed.'));

            return;
        }

        $validated = Validator::make(
            ['close_reason' => $this->close_reason],
            (new ClosePurchaseOrderRequest)->rules()
        )->validate();

        try {
            $this->purchaseOrder = $service->execute(
                (int) session('current_tenant_id'),
                (int) $this->purchaseOrder->id,
                (int) auth()->id(),
                (string) $validated['close_reason'],
            );
            $this->modal('close-po')->close();
            Flux::toast(variant: 'success', text: __('Purchase order closed. Remaining quantities are tagged as PO Close.'));
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function recordPurchaseOrderPayment(RecordSupplierPaymentService $service): void
    {
        Gate::authorize('create', SupplierPayment::class);

        $allowedIds = $this->openPayablesForPurchaseOrder->pluck('id')->map(fn ($id) => (int) $id)->all();

        $allocations = [];
        foreach ($this->allocationAmounts as $apId => $amt) {
            $amt = trim((string) $amt);
            if ($amt === '' || bccomp($amt, '0', 4) !== 1) {
                continue;
            }
            $id = (int) $apId;
            if (! in_array($id, $allowedIds, true)) {
                Flux::toast(variant: 'danger', text: __('Invalid payable allocation for this purchase order.'));

                return;
            }
            $allocations[] = [
                'accounts_payable_id' => $id,
                'amount' => $amt,
            ];
        }

        $paidAt = $this->paid_at !== ''
            ? Carbon::parse($this->paid_at)->toDateTimeString()
            : now()->toDateTimeString();

        $payload = [
            'supplier_id' => (string) $this->purchaseOrder->supplier_id,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'paid_at' => $paidAt,
            'reference' => $this->reference !== '' ? $this->reference : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
            'allocations' => $allocations,
        ];

        Validator::make($payload, (new StoreSupplierPaymentRequest)->rules())->validate();

        try {
            $service->execute(
                (int) session('current_tenant_id'),
                (int) $this->purchaseOrder->supplier_id,
                (string) $payload['amount'],
                $paidAt,
                $payload['reference'],
                $payload['notes'],
                (string) $payload['payment_method'],
                $allocations,
            );
            $this->modal('add-po-payment')->close();
            Flux::toast(variant: 'success', text: __('Supplier payment recorded.'));
            $this->preparePaymentModal();
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function recordSupplierAdvance(RecordSupplierAdvanceService $service): void
    {
        Gate::authorize('create', SupplierAdvance::class);

        if (! $this->canRecordAdvance()) {
            Flux::toast(variant: 'danger', text: __('Advances can only be recorded on open purchase orders.'));

            return;
        }

        $paidAt = $this->advance_paid_at !== ''
            ? Carbon::parse($this->advance_paid_at)->toDateTimeString()
            : now()->toDateTimeString();

        $payload = [
            'purchase_order_id' => (string) $this->purchaseOrder->id,
            'amount' => $this->advance_amount,
            'payment_method' => $this->advance_payment_method,
            'paid_at' => $paidAt,
            'reference' => $this->resolveAdvanceReference(),
            'notes' => $this->advance_notes !== '' ? $this->advance_notes : null,
            'cheque_number' => $this->advance_cheque_number !== '' ? $this->advance_cheque_number : null,
            'cheque_date' => $this->advance_cheque_date !== '' ? $this->advance_cheque_date : null,
            'cheque_bank' => $this->advance_cheque_bank !== '' ? $this->advance_cheque_bank : null,
            'cheque_payee' => $this->advance_cheque_payee !== '' ? $this->advance_cheque_payee : null,
        ];

        Validator::make(
            $payload,
            [
                ...(new StoreSupplierAdvanceRequest)->rules(),
                ...$this->advancePaymentMethodDetailRules(),
            ],
        )->validate();

        try {
            $service->execute(
                (int) session('current_tenant_id'),
                (int) $this->purchaseOrder->id,
                (string) $payload['amount'],
                (string) $payload['payment_method'],
                $paidAt,
                $payload['reference'],
                $payload['notes'],
                $payload['cheque_number'],
                $payload['cheque_date'],
                $payload['cheque_bank'],
                $payload['cheque_payee'],
                auth()->id() !== null ? (int) auth()->id() : null,
            );
            $this->refreshPurchaseOrder();
            $this->modal('add-po-advance')->close();
            Flux::toast(variant: 'success', text: __('Supplier advance recorded.'));
            $this->prepareAdvanceModal();
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function clearAdvancePdc(int $advanceId, ClearSupplierAdvancePdcService $service): void
    {
        Gate::authorize('update', SupplierAdvance::query()->findOrFail($advanceId));

        try {
            $service->execute((int) session('current_tenant_id'), $advanceId);
            $this->refreshPurchaseOrder();
            Flux::toast(variant: 'success', text: __('PDC advance marked as cleared.'));
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }
}; ?>

<div class="flex w-full max-w-6xl flex-1 flex-col gap-6">
    <div class="rounded-2xl border border-zinc-200 bg-linear-to-br from-white to-zinc-50 p-6 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:to-zinc-900">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="space-y-2">
                <div>
                    <flux:text class="text-zinc-600 dark:text-zinc-400 text-xs">Purchase Order No.</flux:text>
                    <flux:heading size="xl" class="text-2xl font-bold font-mono tracking-wide">{{ $purchaseOrder->reference_code }}</flux:heading>
                </div>
                <div>
                    <flux:text class="text-zinc-600 dark:text-zinc-400 text-xs">Supplier</flux:text>
                    <flux:text class="text-zinc-600 dark:text-zinc-400 text-lg">
                        {{ $purchaseOrder->supplier->name }}
                    </flux:text>
                </div>
                <div>
                    <flux:text class="text-zinc-600 dark:text-zinc-400 text-xs">Order date</flux:text>
                    <flux:text class="text-zinc-600 dark:text-zinc-400 text-lg">
                        {{ $purchaseOrder->order_date->translatedFormat('F j, Y') }}
                    </flux:text>
                </div>
                <div class="mt-2 flex flex-wrap gap-2 pt-1">
                    <flux:badge :color="$purchaseOrder->status === PurchaseOrderStatus::Received ? 'emerald' : ($purchaseOrder->status === PurchaseOrderStatus::Cancelled ? 'rose' : ($purchaseOrder->status === PurchaseOrderStatus::PartiallyReceived ? 'amber' : 'teal'))">
                        {{ $purchaseOrder->status === PurchaseOrderStatus::Cancelled ? __('PO Close') : ucfirst(str_replace('_', ' ', $purchaseOrder->status->value)) }}
                    </flux:badge>
                    <flux:badge color="zinc">{{ __(':count line items', ['count' => $purchaseOrder->lines->count()]) }}</flux:badge>
                    <flux:badge color="zinc">{{ __(':count receipts', ['count' => $purchaseOrder->goodsReceipts->count()]) }}</flux:badge>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if (Gate::allows('print', $purchaseOrder))
                    <flux:button
                        :href="route('procurement.purchase-orders.print', $purchaseOrder->id)"
                        target="_blank"
                        variant="filled"
                        class="flex items-center gap-2 cursor-pointer border"
                    >
                        <flux:icon name="printer" class="size-4" />
                        {{ __('Print PO') }}
                    </flux:button>
                @endif
                @if (Gate::allows('create', SupplierAdvance::class) && $this->canRecordAdvance())
                    <flux:modal.trigger name="add-po-advance">
                        <flux:button wire:click="prepareAdvanceModal" variant="primary">{{ __('Record advance') }}</flux:button>
                    </flux:modal.trigger>
                @endif
                @if (Gate::allows('create', SupplierPayment::class) && $this->openPayablesForPurchaseOrder->isNotEmpty())
                    <flux:modal.trigger name="add-po-payment">
                        <flux:button wire:click="preparePaymentModal" variant="filled">{{ __('Add payment') }}</flux:button>
                    </flux:modal.trigger>
                @endif
                @if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::Cancelled, PurchaseOrderStatus::Received], true))
                    <flux:button
                        :variant="Gate::allows('create', SupplierPayment::class) && $this->openPayablesForPurchaseOrder->isNotEmpty() ? 'ghost' : 'primary'"
                        :href="route('procurement.purchase-orders.index', ['receive' => $purchaseOrder->id])"
                        wire:navigate
                    >{{ __('Receive Items') }}</flux:button>
                @endif
                @if (Gate::allows('update', $purchaseOrder) && in_array($purchaseOrder->status, [PurchaseOrderStatus::Confirmed, PurchaseOrderStatus::PartiallyReceived], true))
                    <flux:modal.trigger name="close-po">
                        <flux:button wire:click="prepareCloseModal" variant="danger">{{ __('Close PO') }}</flux:button>
                    </flux:modal.trigger>
                @endif
                <flux:button :href="route('procurement.purchase-orders.index')" variant="ghost" wire:navigate>
                    <flux:icon name="arrow-left" class="size-4" />
                    {{ __('Back') }}
                </flux:button>
            </div>
        </div>
    </div>

    @if ($purchaseOrder->status === PurchaseOrderStatus::Cancelled && $purchaseOrder->close_reason)
        <flux:callout
            color="rose"
            icon="x-circle"
            :heading="__('Purchase order closed')"
            :text="__('Closed on :date by :user. Reason: :reason', [
                'date' => optional($purchaseOrder->closed_at)?->translatedFormat('F j, Y - h:i A') ?? '—',
                'user' => $purchaseOrder->closedByUser?->name ?? __('Unknown user'),
                'reason' => $purchaseOrder->close_reason,
            ])"
        />
    @endif

    @if ($purchaseOrder->notes)
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="mb-1 text-xs uppercase tracking-wide text-zinc-500">{{ __('Order notes') }}</flux:text>
            <flux:text>{{ $purchaseOrder->notes }}</flux:text>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('Purchase Order Items') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Track ordered, received, and outstanding quantities per product.') }}</flux:text>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                            <tr>
                                <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Product') }}</th>
                                <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Ordered') }}</th>
                                <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Received') }}</th>
                                <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Remaining') }}</th>
                                <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Progress') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($this->lineMetrics as $metric)
                                @php
                                    $line = $metric['line'];
                                    $completion = (float) $metric['completion_percentage'];
                                @endphp
                                <tr wire:key="pol-{{ $line->id }}">
                                    <td class="px-6 py-3 font-medium text-zinc-900 dark:text-zinc-100">{!! $line->product->name . ' <span class="font-mono text-zinc-500 dark:text-zinc-400">(' . $line->product->sku . ')</span>' !!}</td>
                                    <td class="px-6 py-3 text-end tabular-nums">{{ \Illuminate\Support\Number::format((float) $metric['ordered'], maxPrecision: 4) }}</td>
                                    <td class="px-6 py-3 text-end tabular-nums text-zinc-600 dark:text-zinc-400">{{ \Illuminate\Support\Number::format((float) $metric['received'], maxPrecision: 4) }}</td>
                                    <td class="px-6 py-3 text-end tabular-nums">{{ \Illuminate\Support\Number::format((float) $metric['remaining'], maxPrecision: 4) }}</td>
                                    <td class="px-6 py-3 text-end">
                                        <span class="inline-flex min-w-20 justify-end rounded-full px-2 py-1 text-xs font-medium {{ $completion >= 100 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : ($completion > 0 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300') }}">
                                            {{ \Illuminate\Support\Number::format($completion, maxPrecision: 1) }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('Actual Items Received') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Track the actual items received against this purchase order.') }}</flux:text>
                </div>
                @if ($purchaseOrder->goodsReceipts->isEmpty())
                    <div class="p-6">
                        <flux:callout icon="archive-box" color="zinc" inline :heading="__('No receipts posted yet')" :text="__('Post the first receipt to start inventory and liability tracking for this order.')" />
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                                <tr>
                                    <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Received at') }}</th>
                                    <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Qty') }}</th>
                                    <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Avg. unit price') }}</th>
                                    <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Payable Total') }}</th>
                                    <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Open Amount') }}</th>
                                    <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Receipt') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach ($purchaseOrder->goodsReceipts->sortByDesc('received_at') as $receipt)
                                    @php
                                        $receiptQty = $receipt->lines->reduce(
                                            fn (string $carry, $line) => bcadd($carry, (string) $line->quantity_received, 4),
                                            '0'
                                        );
                                        $payable = $receipt->accountsPayable;
                                        $receiptOpen = $payable
                                            ? OpenItemStatusResolver::remaining((string) $payable->total_amount, (string) $payable->amount_paid)
                                            : '0';
                                    @endphp
                                    <tr wire:key="gr-{{ $receipt->id }}">
                                        <td class="px-6 py-3 text-zinc-700 dark:text-zinc-300">{{ $receipt->received_at->translatedFormat('F j, Y - h:i A') }}</td>
                                        <td class="px-6 py-3 text-end tabular-nums">{{ \Illuminate\Support\Number::format((float) $receiptQty, maxPrecision: 4) }}</td>
                                        <td class="px-6 py-3 text-end tabular-nums text-zinc-600 dark:text-zinc-400">{{ TenantMoney::format((float) $this->receiptWeightedAverageUnitCost($receipt), null, 4) }}</td>
                                        <td class="px-6 py-3 text-end tabular-nums text-zinc-700 dark:text-zinc-300">{{ TenantMoney::format((float) ($payable?->total_amount ?? 0), null, 2) }}</td>
                                        <td class="px-6 py-3 text-end tabular-nums">{{ TenantMoney::format((float) $receiptOpen, null, 2) }}</td>
                                        <td class="px-6 py-3 text-end">
                                            <flux:button size="xs" variant="ghost" :href="route('procurement.goods-receipts.show', $receipt->id)" wire:navigate>
                                                {{ __('View') }}
                                            </flux:button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            <flux:card class="space-y-2 border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Operational status') }}</flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Use close PO only when remaining quantities should no longer be expected from the supplier.') }}
                </flux:text>
                <div class="space-y-2 text-sm text-zinc-700 dark:text-zinc-300 border-t border-zinc-200 pt-2 dark:border-zinc-700 mt-3">
                    <div class="flex items-center justify-between">
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Current status') }}</flux:text>
                        <flux:text class="text-sm text-zinc-900 dark:text-zinc-100 font-medium">{{ $purchaseOrder->status === PurchaseOrderStatus::Cancelled ? __('PO Close') : ucfirst(str_replace('_', ' ', $purchaseOrder->status->value)) }}</flux:text>
                    </div>
                    <div class="flex items-center justify-between">
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Receipts posted') }}</flux:text>
                        <flux:text class="text-sm text-zinc-900 dark:text-zinc-100 font-medium">{{ $purchaseOrder->goodsReceipts->count() }}</flux:text>
                    </div>
                    <div class="flex items-center justify-between">
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Open payables') }}</flux:text>
                        <flux:text class="text-sm text-zinc-900 dark:text-zinc-100 font-medium">{{ $this->openPayablesForPurchaseOrder->count() }}</flux:text>
                    </div>
                    <div class="flex items-center justify-between">
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Order value') }}</flux:text>
                        <flux:text class="text-sm text-zinc-900 dark:text-zinc-100 font-medium tabular-nums">{{ TenantMoney::format((float) $this->orderTotal, null, 2) }}</flux:text>
                    </div>
                    <div class="flex items-center justify-between">
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Advances recorded') }}</flux:text>
                        <flux:text class="text-sm text-zinc-900 dark:text-zinc-100 font-medium tabular-nums">{{ TenantMoney::format((float) $this->advanceSummary['total'], null, 2) }}</flux:text>
                    </div>
                    <div class="flex items-center justify-between">
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Unapplied advance') }}</flux:text>
                        <flux:text class="text-sm text-zinc-900 dark:text-zinc-100 font-medium tabular-nums">{{ TenantMoney::format((float) $this->advanceSummary['unapplied'], null, 2) }}</flux:text>
                    </div>
                </div>
            </flux:card>
        </div>
    </div>

    @if (Gate::allows('create', SupplierPayment::class) && $this->openPayablesForPurchaseOrder->isEmpty() && $purchaseOrder->goodsReceipts->isNotEmpty())
        <flux:callout
            color="blue"
            icon="information-circle"
            :heading="__('No open payables currently')"
            :text="__('Liabilities are either fully paid or not posted yet. Post accounts payable entries after receipts if needed.')"
        />
    @elseif (Gate::allows('create', SupplierAdvance::class) && $purchaseOrder->goodsReceipts->isEmpty() && bccomp($this->advanceSummary['total'], '0', 4) !== 1)
        <flux:callout
            color="zinc"
            icon="information-circle"
            :heading="__('Payment before receipt')"
            :text="__('Record a supplier advance when payment is issued with the purchase order. After goods are received and payables are posted, cleared advances apply automatically.')"
        />
    @elseif ($this->advanceSummary['scheduled_count'] > 0)
        <flux:callout
            color="amber"
            icon="clock"
            :heading="__('Scheduled PDC advances')"
            :text="__('One or more PDC advances are not cleared yet. Clear them when the cheque is issued or deposited before they can apply to payables.')"
        />
    @endif
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Supplier advances') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Record prepayments before goods are received. Cleared advances auto-apply when accounts payable is posted.') }}
            </flux:text>
        </div>
        @if ($purchaseOrder->supplierAdvances->where('status', '!=', SupplierAdvanceStatus::Cancelled)->isEmpty())
            <div class="p-6">
                <flux:callout
                    icon="banknotes"
                    color="zinc"
                    inline
                    :heading="__('No advances yet')"
                    :text="__('Record an advance when payment is issued with the purchase order, before pickup or delivery.')"
                />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Paid at') }}</th>
                            <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Method') }}</th>
                            <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Amount') }}</th>
                            <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Remaining') }}</th>
                            <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($purchaseOrder->supplierAdvances->where('status', '!=', SupplierAdvanceStatus::Cancelled)->sortByDesc('paid_at') as $advance)
                            <tr wire:key="adv-{{ $advance->id }}">
                                <td class="px-6 py-3 text-zinc-700 dark:text-zinc-300">
                                    {{ $advance->paid_at->translatedFormat('M j, Y - h:i A') }}
                                    @if ($advance->payment_method === SupplierPaymentMethod::Pdc && $advance->cheque_date)
                                        <div class="text-xs text-zinc-500">{{ __('Cheque: :date', ['date' => $advance->cheque_date->translatedFormat('M j, Y')]) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-zinc-700 dark:text-zinc-300">{{ $advance->payment_method->label() }}</td>
                                <td class="px-6 py-3 text-end tabular-nums">{{ TenantMoney::format((float) $advance->amount, null, 2) }}</td>
                                <td class="px-6 py-3 text-end tabular-nums">{{ TenantMoney::format((float) $advance->remainingAmount(), null, 2) }}</td>
                                <td class="px-6 py-3">
                                    <flux:badge :color="$advance->status === SupplierAdvanceStatus::Scheduled ? 'amber' : ($advance->isFullyApplied() ? 'emerald' : 'teal')">
                                        {{ $advance->status === SupplierAdvanceStatus::Scheduled ? $advance->status->label() : ($advance->isFullyApplied() ? __('Applied') : __('Available')) }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-3 text-end">
                                    @if ($advance->status === SupplierAdvanceStatus::Scheduled && Gate::allows('update', $advance))
                                        <flux:button size="xs" variant="primary" wire:click="clearAdvancePdc({{ $advance->id }})">
                                            {{ __('Clear PDC') }}
                                        </flux:button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if (Gate::allows('update', $purchaseOrder) && in_array($purchaseOrder->status, [PurchaseOrderStatus::Confirmed, PurchaseOrderStatus::PartiallyReceived], true))
        <flux:modal name="close-po" class="max-w-lg">
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Close purchase order') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ __('Closing will mark remaining unreceived quantities as PO Close and lock this PO from further receiving.') }}
                    </flux:text>
                </div>

                <form wire:submit="closePurchaseOrder" class="space-y-4">
                    <flux:textarea
                        wire:model="close_reason"
                        :label="__('Reason for closure')"
                        rows="3"
                        required
                        :placeholder="__('Example: Supplier discontinued remaining items and approved PO Close.')"
                    />
                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button variant="danger" type="submit">{{ __('Close purchase order') }}</flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>
    @endif

    @if (Gate::allows('create', SupplierAdvance::class) && $this->canRecordAdvance())
        <flux:modal name="add-po-advance" class="max-w-lg">
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Record supplier advance') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ __('Issue payment before goods are received. Maximum remaining: :amount.', [
                            'amount' => TenantMoney::format(
                                max(0, (float) bcsub($this->orderTotal, $this->advanceSummary['total'], 4)),
                                null,
                                2,
                            ),
                        ]) }}
                    </flux:text>
                </div>

                <form wire:submit="recordSupplierAdvance" class="flex flex-col gap-4">
                    <flux:input
                        wire:model="advance_amount"
                        :label="__('Advance amount')"
                        type="text"
                        inputmode="decimal"
                        required
                    />

                    <flux:select wire:model.live="advance_payment_method" :label="__('Payment method')" required>
                        @foreach (SupplierPaymentMethod::cases() as $method)
                            <flux:select.option :value="$method->value">{{ $method->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    @if ($advance_payment_method === SupplierPaymentMethod::Pdc->value)
                        <div class="flex flex-col gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <flux:heading size="sm">{{ __('Cheque details') }}</flux:heading>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:input wire:model="advance_cheque_number" :label="__('Cheque number')" type="text" required />
                                <flux:input wire:model="advance_cheque_date" :label="__('Cheque date')" type="date" required />
                                <flux:input wire:model="advance_cheque_bank" :label="__('Bank')" type="text" required class="sm:col-span-2" />
                                <flux:input wire:model="advance_cheque_payee" :label="__('Payee (optional)')" type="text" class="sm:col-span-2" />
                            </div>
                            <flux:text class="text-xs text-zinc-500">
                                {{ __('Future-dated cheques stay scheduled until you clear them on or after the cheque date.') }}
                            </flux:text>
                        </div>
                    @endif

                    <flux:input wire:model="advance_paid_at" :label="__('Paid at')" type="datetime-local" required />

                    <flux:input wire:model="advance_reference" :label="__('Reference (optional)')" type="text" />

                    <flux:textarea wire:model="advance_notes" :label="__('Notes')" rows="2" />

                    <div class="flex flex-wrap justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button variant="primary" type="submit">{{ __('Save advance') }}</flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>
    @endif

    @if (Gate::allows('create', SupplierPayment::class) && $this->openPayablesForPurchaseOrder->isNotEmpty())
        <flux:modal name="add-po-payment" class="max-w-lg">
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Add payment') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ __('Allocate to open payables for :supplier from this order. Total allocations must match the payment amount.', ['supplier' => $purchaseOrder->supplier->name]) }}
                    </flux:text>
                </div>

                <form wire:submit="recordPurchaseOrderPayment" class="flex flex-col gap-4">
                    <flux:input
                        wire:model="amount"
                        :label="__('Payment amount')"
                        type="text"
                        inputmode="decimal"
                        required
                    />

                    <flux:select wire:model="payment_method" :label="__('Payment method')" required>
                        @foreach (SupplierPaymentMethod::cases() as $method)
                            <flux:select.option :value="$method->value">{{ $method->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="paid_at" :label="__('Paid at')" type="datetime-local" required />

                    <flux:input wire:model="reference" :label="__('Reference (optional)')" type="text" />

                    <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />

                    <div>
                        <flux:heading size="sm" class="mb-2">{{ __('Allocate to payables for this order') }}</flux:heading>
                        <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                                    <tr>
                                        <th class="px-4 py-2 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Payable') }}</th>
                                        <th class="px-4 py-2 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Remaining') }}</th>
                                        <th class="px-4 py-2 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @foreach ($this->openPayablesForPurchaseOrder as $ap)
                                        @php
                                            $rem = OpenItemStatusResolver::remaining(
                                                (string) $ap->total_amount,
                                                (string) $ap->amount_paid,
                                            );
                                        @endphp
                                        <tr wire:key="po-pay-ap-{{ $ap->id }}">
                                            <td class="px-4 py-2 font-medium text-zinc-900 dark:text-zinc-100">#{{ $ap->id }}</td>
                                            <td class="px-4 py-2 text-end tabular-nums text-zinc-600 dark:text-zinc-400">{{ TenantMoney::format((float) $rem, null, 4) }}</td>
                                            <td class="px-4 py-2 text-end">
                                                <flux:input
                                                    class="max-w-40 ms-auto"
                                                    wire:model="allocationAmounts.{{ $ap->id }}"
                                                    type="text"
                                                    inputmode="decimal"
                                                    :placeholder="'0'"
                                                />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button variant="primary" type="submit">{{ __('Save payment') }}</flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>
    @endif
</div>
