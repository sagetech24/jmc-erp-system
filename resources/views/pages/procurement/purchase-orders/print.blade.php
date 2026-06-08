<?php

use App\Models\PurchaseOrder;
use App\Models\Tenant;
use App\Support\TenantMoney;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::print')]
#[Title('Print purchase order')]
class extends Component {
    public PurchaseOrder $purchaseOrder;

    public Tenant $tenant;

    public string $orderTotal = '0';

    public function mount(int $id): void
    {
        $tenantId = (int) session('current_tenant_id');
        $this->purchaseOrder = PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->with(['supplier', 'lines.product', 'rfq'])
            ->findOrFail($id);

        Gate::authorize('print', $this->purchaseOrder);

        $this->tenant = Tenant::query()->findOrFail($tenantId);
        $this->orderTotal = $this->calculateOrderTotal();
    }

    private function calculateOrderTotal(): string
    {
        $total = '0';

        foreach ($this->purchaseOrder->lines as $line) {
            $unitCost = $line->unit_cost !== null ? (string) $line->unit_cost : '0';
            $lineTotal = bcmul((string) $line->quantity_ordered, $unitCost, 4);
            $total = bcadd($total, $lineTotal, 4);
        }

        return $total;
    }
}; ?>

<div class="mx-auto max-w-4xl px-4 py-8 print:max-w-none print:px-0 print:py-0">
    <div class="print-hidden mb-6 flex flex-wrap items-center justify-between gap-3">
        <flux:button
            :href="route('procurement.purchase-orders.show', $purchaseOrder->id)"
            variant="ghost"
            wire:navigate
        >
            <flux:icon name="arrow-left" class="size-4" />
            {{ __('Back to purchase order') }}
        </flux:button>
        <flux:button variant="primary" onclick="window.print()">
            <flux:icon name="printer" class="size-4" />
            {{ __('Print') }}
        </flux:button>
    </div>

    <article class="print-document rounded-xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 print:rounded-none print:p-0">
        <header class="border-b border-zinc-200 pb-6 dark:border-zinc-700">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <flux:heading size="xl" class="text-2xl font-bold">{{ $tenant->name }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Purchase Order') }}</flux:text>
                </div>
                <div class="text-start sm:text-end">
                    <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('PO Number') }}</flux:text>
                    <flux:heading size="lg" class="font-mono text-xl">{{ $purchaseOrder->reference_code }}</flux:heading>
                    <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Order date: :date', ['date' => $purchaseOrder->order_date->translatedFormat('F j, Y')]) }}
                    </flux:text>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Status: :status', ['status' => ucfirst(str_replace('_', ' ', $purchaseOrder->status->value))]) }}
                    </flux:text>
                </div>
            </div>
        </header>

        <section class="grid gap-6 border-b border-zinc-200 py-6 dark:border-zinc-700 sm:grid-cols-2">
            <div>
                <flux:text class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Supplier') }}</flux:text>
                <flux:text class="font-medium text-zinc-900 dark:text-zinc-100">{{ $purchaseOrder->supplier->name }}</flux:text>
                @if ($purchaseOrder->supplier->code)
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Code: :code', ['code' => $purchaseOrder->supplier->code]) }}</flux:text>
                @endif
                @if ($purchaseOrder->supplier->email)
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ $purchaseOrder->supplier->email }}</flux:text>
                @endif
                @if ($purchaseOrder->supplier->phone)
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ $purchaseOrder->supplier->phone }}</flux:text>
                @endif
                @if ($purchaseOrder->supplier->address)
                    <flux:text class="mt-1 whitespace-pre-line text-sm text-zinc-600 dark:text-zinc-400">{{ $purchaseOrder->supplier->address }}</flux:text>
                @endif
            </div>
            <div>
                <flux:text class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Order details') }}</flux:text>
                @if ($purchaseOrder->rfq?->reference_code)
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('RFQ: :code', ['code' => $purchaseOrder->rfq->reference_code]) }}
                    </flux:text>
                @endif
                @if ($purchaseOrder->supplier->payment_terms)
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Payment terms: :terms', ['terms' => $purchaseOrder->supplier->payment_terms]) }}
                    </flux:text>
                @endif
                @if ($purchaseOrder->supplier->tax_id)
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Tax ID: :id', ['id' => $purchaseOrder->supplier->tax_id]) }}
                    </flux:text>
                @endif
            </div>
        </section>

        <section class="py-6">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start">
                        <th class="py-3 pe-4 font-medium text-zinc-600 dark:text-zinc-400">#</th>
                        <th class="py-3 pe-4 font-medium text-zinc-600 dark:text-zinc-400">{{ __('Product') }}</th>
                        <th class="py-3 pe-4 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Qty') }}</th>
                        <th class="py-3 pe-4 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Unit cost') }}</th>
                        <th class="py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Line total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($purchaseOrder->lines as $index => $line)
                        @php
                            $unitCost = $line->unit_cost !== null ? (string) $line->unit_cost : '0';
                            $lineTotal = bcmul((string) $line->quantity_ordered, $unitCost, 4);
                        @endphp
                        <tr wire:key="print-line-{{ $line->id }}">
                            <td class="py-3 pe-4 tabular-nums text-zinc-500">{{ $index + 1 }}</td>
                            <td class="py-3 pe-4">
                                <flux:text class="font-medium text-zinc-900 dark:text-zinc-100">{{ $line->product->name }}</flux:text>
                                <flux:text class="font-mono text-xs text-zinc-500">{{ $line->product->sku }}</flux:text>
                            </td>
                            <td class="py-3 pe-4 text-end tabular-nums">{{ \Illuminate\Support\Number::format((float) $line->quantity_ordered, maxPrecision: 4) }}</td>
                            <td class="py-3 pe-4 text-end tabular-nums">{{ TenantMoney::format((float) $unitCost, null, 4) }}</td>
                            <td class="py-3 text-end tabular-nums font-medium">{{ TenantMoney::format((float) $lineTotal, null, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="pt-4 text-end font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Order total') }}</td>
                        <td class="pt-4 text-end font-semibold tabular-nums">{{ TenantMoney::format((float) $orderTotal, null, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </section>

        @if ($purchaseOrder->notes)
            <section class="border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <flux:text class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Notes') }}</flux:text>
                <flux:text class="whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $purchaseOrder->notes }}</flux:text>
            </section>
        @endif

        <footer class="mt-8 border-t border-zinc-200 pt-4 text-xs text-zinc-500 dark:border-zinc-700">
            {{ __('Printed on :date', ['date' => now()->timezone(config('app.timezone'))->translatedFormat('F j, Y - h:i A')]) }}
        </footer>
    </article>
</div>
