<?php

use App\Domains\Accounting\Services\DeleteSupplierPaymentRunService;
use App\Enums\SupplierPaymentRunStatus;
use App\Models\SupplierPaymentRun;
use App\Support\TenantMoney;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app', ['title' => 'Payment runs'])]
#[Title('Payment runs')]
class extends Component {
    use WithPagination;

    public string $statusFilter = '';
    public string $scheduledFrom = '';
    public string $scheduledTo = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', SupplierPaymentRun::class);
    }

    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedScheduledFrom(): void { $this->resetPage(); }
    public function updatedScheduledTo(): void { $this->resetPage(); }

    public function deleteRun(int $runId, DeleteSupplierPaymentRunService $service): void
    {
        $run = SupplierPaymentRun::query()
            ->where('tenant_id', (int) session('current_tenant_id'))
            ->whereKey($runId)
            ->firstOrFail();

        Gate::authorize('delete', $run);

        try {
            $service->execute((int) session('current_tenant_id'), $runId);
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Payment run deleted.'));
    }

    public function canDeleteRun(SupplierPaymentRun $run): bool
    {
        return in_array($run->status, [SupplierPaymentRunStatus::Draft, SupplierPaymentRunStatus::Approved, SupplierPaymentRunStatus::Cancelled], true);
    }

    public function getRunsProperty()
    {
        return SupplierPaymentRun::query()
            ->where('tenant_id', (int) session('current_tenant_id'))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->scheduledFrom !== '', fn ($query) => $query->whereDate('scheduled_for', '>=', $this->scheduledFrom))
            ->when($this->scheduledTo !== '', fn ($query) => $query->whereDate('scheduled_for', '<=', $this->scheduledTo))
            ->withCount('items')
            ->latest()
            ->paginate(15);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Supplier payment runs') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Review batch runs, control execution, and audit payment outcomes.') }}</flux:text>
        </div>
        <flux:button :href="route('accounting.payables.index')" variant="primary" wire:navigate>{{ __('Create new run') }}</flux:button>
    </div>

    <flux:card class="p-6">
        <div class="grid gap-4 sm:grid-cols-3">
            <flux:select wire:model.live="statusFilter" :label="__('Status')">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach (SupplierPaymentRunStatus::cases() as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live="scheduledFrom" :label="__('Scheduled from')" type="date" />
            <flux:input wire:model.live="scheduledTo" :label="__('Scheduled to')" type="date" />
        </div>
    </flux:card>

    <flux:card class="p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-6 py-3 text-start">{{ __('Reference') }}</th>
                        <th class="px-6 py-3 text-start">{{ __('Status') }}</th>
                        <th class="px-6 py-3 text-start">{{ __('Scheduled') }}</th>
                        <th class="px-6 py-3 text-end">{{ __('Items') }}</th>
                        <th class="px-6 py-3 text-end">{{ __('Proposed') }}</th>
                        <th class="px-6 py-3 text-end">{{ __('Executed') }}</th>
                        <th class="px-6 py-3 text-end"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->runs as $run)
                        <tr>
                            <td class="px-6 py-3 font-medium">{{ $run->reference_code }}</td>
                            <td class="px-6 py-3">{{ $run->status->label() }}</td>
                            <td class="px-6 py-3">{{ $run->scheduled_for->format('Y-m-d') }}</td>
                            <td class="px-6 py-3 text-end">{{ $run->items_count }}</td>
                            <td class="px-6 py-3 text-end tabular-nums">{{ TenantMoney::format((float) $run->proposed_amount, null, 4) }}</td>
                            <td class="px-6 py-3 text-end tabular-nums">{{ TenantMoney::format((float) $run->executed_amount, null, 4) }}</td>
                            <td class="px-6 py-3 text-end">
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" :href="route('accounting.payment-runs.show', ['id' => $run->id])" wire:navigate>{{ __('View') }}</flux:button>
                                    @if ($this->canDeleteRun($run))
                                        <flux:button
                                            size="sm"
                                            variant="danger"
                                            wire:click="deleteRun({{ $run->id }})"
                                            wire:confirm="{{ __('Permanently delete payment run :code? This cannot be undone.', ['code' => $run->reference_code]) }}"
                                        >{{ __('Delete') }}</flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-8 text-center text-zinc-500">{{ __('No payment runs found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->runs->hasPages())
            <div class="border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">{{ $this->runs->links() }}</div>
        @endif
    </flux:card>
</div>
