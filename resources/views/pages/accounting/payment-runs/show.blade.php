<?php

use App\Domains\Accounting\Services\ApproveSupplierPaymentRunService;
use App\Domains\Accounting\Services\CancelSupplierPaymentRunService;
use App\Domains\Accounting\Services\DeleteSupplierPaymentRunService;
use App\Domains\Accounting\Services\ExecuteSupplierPaymentRunService;
use App\Enums\SupplierPaymentRunStatus;
use App\Models\SupplierPaymentRun;
use App\Support\TenantMoney;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Payment run detail'])]
#[Title('Payment run detail')]
class extends Component {
    public int $id;

    public function mount(int $id): void
    {
        $this->id = $id;
        Gate::authorize('view', $this->run);
    }

    public function approveRun(ApproveSupplierPaymentRunService $service): void
    {
        Gate::authorize('update', $this->run);

        try {
            $service->execute((int) session('current_tenant_id'), $this->id, (int) auth()->id());
            Flux::toast(variant: 'success', text: __('Payment run approved.'));
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function executeRun(ExecuteSupplierPaymentRunService $service): void
    {
        Gate::authorize('update', $this->run);

        try {
            $service->execute((int) session('current_tenant_id'), $this->id);
            Flux::toast(variant: 'success', text: __('Payment run executed.'));
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function cancelRun(CancelSupplierPaymentRunService $service): void
    {
        Gate::authorize('update', $this->run);

        try {
            $service->execute((int) session('current_tenant_id'), $this->id);
            Flux::toast(variant: 'success', text: __('Payment run cancelled.'));
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function deleteRun(DeleteSupplierPaymentRunService $service): void
    {
        Gate::authorize('delete', $this->run);

        try {
            $service->execute((int) session('current_tenant_id'), $this->id);
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Payment run deleted.'));
        $this->redirect(route('accounting.payment-runs.index', absolute: false), navigate: true);
    }

    public function getRunProperty(): SupplierPaymentRun
    {
        return SupplierPaymentRun::query()
            ->where('tenant_id', (int) session('current_tenant_id'))
            ->with(['items.accountsPayable.supplier', 'items.supplierPayment'])
            ->whereKey($this->id)
            ->firstOrFail();
    }

    public function canApprove(): bool
    {
        return $this->run->status === SupplierPaymentRunStatus::Draft;
    }

    public function canExecute(): bool
    {
        return $this->run->status === SupplierPaymentRunStatus::Approved;
    }

    public function canCancel(): bool
    {
        return in_array($this->run->status, [SupplierPaymentRunStatus::Draft, SupplierPaymentRunStatus::Approved], true);
    }

    public function canDelete(): bool
    {
        if (! in_array($this->run->status, [SupplierPaymentRunStatus::Draft, SupplierPaymentRunStatus::Approved, SupplierPaymentRunStatus::Cancelled], true)) {
            return false;
        }

        return ! $this->run->items->contains(fn ($item) => $item->supplier_payment_id !== null);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Payment run :code', ['code' => $this->run->reference_code]) }}</flux:heading>
            <flux:text class="mt-1">{{ __('Status: :status', ['status' => $this->run->status->label()]) }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button :href="route('accounting.payment-runs.index')" variant="ghost" wire:navigate>{{ __('Back') }}</flux:button>
            @if ($this->canApprove())
                <flux:button wire:click="approveRun" variant="primary">{{ __('Approve') }}</flux:button>
            @endif
            @if ($this->canExecute())
                <flux:button wire:click="executeRun" variant="primary">{{ __('Execute run') }}</flux:button>
            @endif
            @if ($this->canCancel())
                <flux:button wire:click="cancelRun" variant="danger">{{ __('Cancel') }}</flux:button>
            @endif
            @if ($this->canDelete())
                <flux:button
                    wire:click="deleteRun"
                    wire:confirm="{{ __('Permanently delete this payment run? This cannot be undone.') }}"
                    variant="danger"
                >{{ __('Delete') }}</flux:button>
            @endif
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="p-4"><flux:text size="sm">{{ __('Scheduled for') }}</flux:text><flux:heading size="lg">{{ $this->run->scheduled_for->format('Y-m-d') }}</flux:heading></flux:card>
        <flux:card class="p-4"><flux:text size="sm">{{ __('Items') }}</flux:text><flux:heading size="lg">{{ $this->run->items->count() }}</flux:heading></flux:card>
        <flux:card class="p-4"><flux:text size="sm">{{ __('Proposed amount') }}</flux:text><flux:heading size="lg">{{ TenantMoney::format((float) $this->run->proposed_amount, null, 4) }}</flux:heading></flux:card>
        <flux:card class="p-4"><flux:text size="sm">{{ __('Executed amount') }}</flux:text><flux:heading size="lg">{{ TenantMoney::format((float) $this->run->executed_amount, null, 4) }}</flux:heading></flux:card>
    </div>

    <flux:card class="p-0 overflow-hidden">
        <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Run items') }}</flux:heading>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-6 py-3 text-start">{{ __('AP') }}</th>
                        <th class="px-6 py-3 text-start">{{ __('Supplier') }}</th>
                        <th class="px-6 py-3 text-start">{{ __('Due date') }}</th>
                        <th class="px-6 py-3 text-end">{{ __('Planned') }}</th>
                        <th class="px-6 py-3 text-end">{{ __('Executed') }}</th>
                        <th class="px-6 py-3 text-start">{{ __('Payment record') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->run->items as $item)
                        <tr>
                            <td class="px-6 py-3">#{{ $item->accounts_payable_id }}</td>
                            <td class="px-6 py-3">{{ $item->accountsPayable->supplier->name }}</td>
                            <td class="px-6 py-3">{{ $item->accountsPayable->due_date?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-6 py-3 text-end tabular-nums">{{ TenantMoney::format((float) $item->planned_amount, null, 4) }}</td>
                            <td class="px-6 py-3 text-end tabular-nums">{{ TenantMoney::format((float) $item->executed_amount, null, 4) }}</td>
                            <td class="px-6 py-3">{{ $item->supplier_payment_id ? '#'.$item->supplier_payment_id : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-zinc-500">{{ __('No run items found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</div>
