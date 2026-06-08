<?php

use App\Domains\Accounting\Services\BuildSupplierPaymentRunService;
use App\Domains\Accounting\Services\PostAccountsPayableFromGoodsReceiptService;
use App\Enums\AccountingOpenItemStatus;
use App\Enums\GoodsReceiptStatus;
use App\Enums\SupplierPaymentMethod;
use App\Http\Requests\PostAccountsPayableRequest;
use App\Http\Requests\StoreSupplierPaymentRunRequest;
use App\Models\AccountsPayable;
use App\Models\GoodsReceipt;
use App\Models\Supplier;
use App\Models\SupplierPaymentRun;
use App\Support\TenantMoney;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app', ['title' => 'Accounts payable'])]
#[Title('Accounts payable')]
class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $supplierFilter = '';
    public string $dueFrom = '';
    public string $dueTo = '';
    public string $activeTab = 'all';
    /** @var array<int|string, bool> */
    public array $selectedPayables = [];
    public string $runScheduledFor = '';
    public string $runPaymentMethod = '';
    public string $runSupplierId = '';
    public string $runDueDateTo = '';
    public string $runNotes = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', AccountsPayable::class);
        Gate::authorize('viewAny', SupplierPaymentRun::class);

        $this->runScheduledFor = now()->toDateString();
        $this->runPaymentMethod = SupplierPaymentMethod::BankTransfer->value;
    }

    public function hasDueDateColumn(): bool
    {
        return Schema::hasColumn('accounts_payable', 'due_date');
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedSupplierFilter(): void { $this->resetPage(); }
    public function updatedDueFrom(): void { $this->resetPage(); }
    public function updatedDueTo(): void { $this->resetPage(); }
    public function updatedActiveTab(): void { $this->resetPage(); }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function postPayable(int $goodsReceiptId, PostAccountsPayableFromGoodsReceiptService $service): void
    {
        Gate::authorize('create', AccountsPayable::class);

        $validated = Validator::make(['goods_receipt_id' => $goodsReceiptId], (new PostAccountsPayableRequest)->rules())->validate();

        $receipt = GoodsReceipt::query()
            ->where('tenant_id', (int) session('current_tenant_id'))
            ->whereKey((int) $validated['goods_receipt_id'])
            ->firstOrFail();
        Gate::authorize('view', $receipt);

        try {
            $service->execute((int) session('current_tenant_id'), (int) $validated['goods_receipt_id']);
            Flux::toast(variant: 'success', text: __('Posted to accounts payable.'));
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }

        $this->resetPage();
    }

    public function clearSelection(): void
    {
        $this->selectedPayables = [];
    }

    public function createPaymentRun(BuildSupplierPaymentRunService $service): void
    {
        Gate::authorize('create', SupplierPaymentRun::class);

        $selectedIds = array_values(array_map('intval', array_keys(array_filter($this->selectedPayables))));
        $payload = [
            'scheduled_for' => $this->runScheduledFor,
            'payment_method' => $this->runPaymentMethod !== '' ? $this->runPaymentMethod : null,
            'supplier_id' => $this->runSupplierId !== '' ? (int) $this->runSupplierId : null,
            'due_date_to' => $this->runDueDateTo !== '' ? $this->runDueDateTo : null,
            'selected_payable_ids' => $selectedIds !== [] ? $selectedIds : null,
            'notes' => $this->runNotes !== '' ? $this->runNotes : null,
        ];
        Validator::make($payload, (new StoreSupplierPaymentRunRequest)->rules())->validate();

        try {
            $run = $service->execute(
                (int) session('current_tenant_id'),
                (int) auth()->id(),
                $payload['scheduled_for'],
                $payload['payment_method'],
                $payload['supplier_id'],
                $payload['due_date_to'],
                $payload['notes'],
                $selectedIds,
            );
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->clearSelection();
        Flux::toast(variant: 'success', text: __('Payment run :code created with :count items.', ['code' => $run->reference_code, 'count' => $run->items->count()]));
        $this->redirect(route('accounting.payment-runs.show', ['id' => $run->id], absolute: false), navigate: true);
    }

    private function payablesQuery(): Builder
    {
        $tenantId = (int) session('current_tenant_id');
        $query = AccountsPayable::query()->where('tenant_id', $tenantId);

        if ($this->activeTab === 'due') {
            if ($this->hasDueDateColumn()) {
                $query->whereDate('due_date', '<=', now()->toDateString())
                    ->whereDate('due_date', '>=', now()->subDays(7)->toDateString());
            } else {
                $query->whereDate('posted_at', '<=', now()->toDateString())
                    ->whereDate('posted_at', '>=', now()->subDays(7)->toDateString());
            }
        } elseif ($this->activeTab === 'overdue') {
            if ($this->hasDueDateColumn()) {
                $query->whereDate('due_date', '<', now()->toDateString());
            } else {
                $query->whereDate('posted_at', '<', now()->subDays(30)->toDateString());
            }
            $query->whereIn('status', [AccountingOpenItemStatus::Open, AccountingOpenItemStatus::Partial]);
        } elseif ($this->activeTab === 'payment_ready') {
            if ($this->hasDueDateColumn()) {
                $query->whereNotNull('due_date');
            }
            $query->whereIn('status', [AccountingOpenItemStatus::Open, AccountingOpenItemStatus::Partial]);
        }

        return $query
            ->when($this->search !== '', function (Builder $builder): void {
                $builder->where(function (Builder $nested): void {
                    $nested->where('invoice_number', 'like', '%'.$this->search.'%')
                        ->orWhere('id', 'like', '%'.$this->search.'%')
                        ->orWhereHas('supplier', fn (Builder $supplierQ) => $supplierQ->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->statusFilter !== '', fn (Builder $builder) => $builder->where('status', $this->statusFilter))
            ->when($this->supplierFilter !== '', fn (Builder $builder) => $builder->where('supplier_id', (int) $this->supplierFilter))
            ->when($this->hasDueDateColumn() && $this->dueFrom !== '', fn (Builder $builder) => $builder->whereDate('due_date', '>=', $this->dueFrom))
            ->when($this->hasDueDateColumn() && $this->dueTo !== '', fn (Builder $builder) => $builder->whereDate('due_date', '<=', $this->dueTo));
    }

    public function getPayablesProperty()
    {
        return $this->payablesQuery()
            ->with('supplier', 'goodsReceipt')
            ->when(
                $this->hasDueDateColumn(),
                fn (Builder $builder) => $builder->orderByRaw('due_date is null')->orderBy('due_date'),
                fn (Builder $builder) => $builder->orderByDesc('posted_at')
            )
            ->orderByDesc('posted_at')
            ->paginate(15);
    }

    public function getKpisProperty(): array
    {
        $tenantId = (int) session('current_tenant_id');
        $openStatuses = [AccountingOpenItemStatus::Open->value, AccountingOpenItemStatus::Partial->value];

        $openTotal = (string) AccountsPayable::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', $openStatuses)
            ->selectRaw('coalesce(sum(total_amount - amount_paid), 0) as balance')
            ->value('balance');
        $overdue = (string) AccountsPayable::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', $openStatuses)
            ->when(
                $this->hasDueDateColumn(),
                fn (Builder $builder) => $builder->whereDate('due_date', '<', now()->toDateString()),
                fn (Builder $builder) => $builder->whereDate('posted_at', '<', now()->subDays(30)->toDateString())
            )
            ->selectRaw('coalesce(sum(total_amount - amount_paid), 0) as balance')
            ->value('balance');
        $dueThisWeek = (string) AccountsPayable::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', $openStatuses)
            ->when(
                $this->hasDueDateColumn(),
                fn (Builder $builder) => $builder->whereBetween('due_date', [now()->toDateString(), now()->addDays(7)->toDateString()]),
                fn (Builder $builder) => $builder->whereBetween('posted_at', [now()->toDateString(), now()->addDays(7)->toDateString()])
            )
            ->selectRaw('coalesce(sum(total_amount - amount_paid), 0) as balance')
            ->value('balance');
        $readyCount = (int) AccountsPayable::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', $openStatuses)
            ->when($this->hasDueDateColumn(), fn (Builder $builder) => $builder->whereNotNull('due_date'))
            ->count();

        return compact('openTotal', 'overdue', 'dueThisWeek', 'readyCount');
    }

    public function getSuppliersProperty()
    {
        return Supplier::query()
            ->where('tenant_id', (int) session('current_tenant_id'))
            ->orderBy('name')
            ->get();
    }

    public function getPendingReceiptsProperty()
    {
        return GoodsReceipt::query()
            ->where('tenant_id', (int) session('current_tenant_id'))
            ->where('status', GoodsReceiptStatus::Posted)
            ->whereDoesntHave('accountsPayable')
            ->with(['purchaseOrder.supplier'])
            ->latest('received_at')
            ->get();
    }

    public function getRecentPaymentRunsProperty()
    {
        return SupplierPaymentRun::query()
            ->where('tenant_id', (int) session('current_tenant_id'))
            ->latest()
            ->limit(5)
            ->get();
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Accounts payable command center') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Manage liabilities, prioritize due invoices, and prepare batch payment runs with tenant-safe controls.') }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button :href="route('accounting.supplier-payments.create')" variant="primary" wire:navigate>{{ __('Record supplier payment') }}</flux:button>
            <flux:button :href="route('accounting.payment-runs.index')" variant="filled" wire:navigate>{{ __('Open payment runs') }}</flux:button>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="p-4"><flux:text size="sm">{{ __('Open AP') }}</flux:text><flux:heading size="lg">{{ TenantMoney::format((float) $this->kpis['openTotal'], null, 4) }}</flux:heading></flux:card>
        <flux:card class="p-4"><flux:text size="sm">{{ __('Overdue') }}</flux:text><flux:heading size="lg">{{ TenantMoney::format((float) $this->kpis['overdue'], null, 4) }}</flux:heading></flux:card>
        <flux:card class="p-4"><flux:text size="sm">{{ __('Due in 7 days') }}</flux:text><flux:heading size="lg">{{ TenantMoney::format((float) $this->kpis['dueThisWeek'], null, 4) }}</flux:heading></flux:card>
        <flux:card class="p-4"><flux:text size="sm">{{ __('Payment-ready items') }}</flux:text><flux:heading size="lg">{{ $this->kpis['readyCount'] }}</flux:heading></flux:card>
    </div>

    <flux:card class="p-6">
        <div class="flex flex-wrap gap-2">
            @foreach (['all' => __('All'), 'due' => __('Due'), 'overdue' => __('Overdue'), 'payment_ready' => __('Payment ready')] as $tab => $label)
                <flux:button size="sm" :variant="$activeTab === $tab ? 'primary' : 'outline'" wire:click="setTab('{{ $tab }}')">{{ $label }}</flux:button>
            @endforeach
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <flux:input wire:model.live.debounce.400ms="search" :label="__('Search')" placeholder="{{ __('Invoice # / Supplier / AP ID') }}" />
            <flux:select wire:model.live="statusFilter" :label="__('Status')">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach (AccountingOpenItemStatus::cases() as $status)
                    <flux:select.option :value="$status->value">{{ ucfirst($status->value) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="supplierFilter" :label="__('Supplier')">
                <flux:select.option value="">{{ __('All suppliers') }}</flux:select.option>
                @foreach ($this->suppliers as $supplier)
                    <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live="dueFrom" :label="__('Due from')" type="date" />
            <flux:input wire:model.live="dueTo" :label="__('Due to')" type="date" />
        </div>
    </flux:card>

    @if ($this->pendingReceipts->isNotEmpty())
        <flux:card class="p-0 overflow-hidden">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Receipts ready for AP posting') }}</flux:heading>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50"><tr><th class="px-6 py-3 text-start">{{ __('Receipt') }}</th><th class="px-6 py-3 text-start">{{ __('Supplier') }}</th><th class="px-6 py-3 text-start">{{ __('Invoice ref') }}</th><th class="px-6 py-3 text-start">{{ __('Received') }}</th><th class="px-6 py-3 text-end"></th></tr></thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($this->pendingReceipts as $receipt)
                            <tr wire:key="gr-pend-{{ $receipt->id }}">
                                <td class="px-6 py-3">#{{ $receipt->id }}</td>
                                <td class="px-6 py-3">{{ $receipt->purchaseOrder->supplier->name }}</td>
                                <td class="px-6 py-3">{{ $receipt->supplier_invoice_reference ?? '—' }}</td>
                                <td class="px-6 py-3">{{ $receipt->received_at->format('Y-m-d H:i') }}</td>
                                <td class="px-6 py-3 text-end"><flux:button size="sm" variant="primary" wire:click="postPayable({{ $receipt->id }})">{{ __('Post to AP') }}</flux:button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif

    <flux:card class="p-0 overflow-hidden">
        <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700 flex items-center justify-between">
            <flux:heading size="lg">{{ __('Payables workspace') }}</flux:heading>
            <flux:text>{{ __('Selected: :count', ['count' => count(array_filter($selectedPayables))]) }}</flux:text>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-4 py-3"></th><th class="px-4 py-3 text-start">{{ __('AP') }}</th><th class="px-4 py-3 text-start">{{ __('Supplier') }}</th><th class="px-4 py-3 text-start">{{ __('Invoice') }}</th><th class="px-4 py-3 text-start">{{ __('Due date') }}</th><th class="px-4 py-3 text-end">{{ __('Remaining') }}</th><th class="px-4 py-3 text-start">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->payables as $payable)
                        @php $remaining = \App\Domains\Accounting\Support\OpenItemStatusResolver::remaining((string) $payable->total_amount, (string) $payable->amount_paid); @endphp
                        <tr wire:key="ap-{{ $payable->id }}">
                            <td class="px-4 py-3"><input type="checkbox" wire:model.live="selectedPayables.{{ $payable->id }}"></td>
                            <td class="px-4 py-3 font-medium">#{{ $payable->id }}</td>
                            <td class="px-4 py-3">{{ $payable->supplier->name }}</td>
                            <td class="px-4 py-3">{{ $payable->invoice_number ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $payable->due_date?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3 text-end tabular-nums">{{ TenantMoney::format((float) $remaining, null, 4) }}</td>
                            <td class="px-4 py-3 capitalize">{{ $payable->status->value }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-8 text-center text-zinc-500">{{ __('No payables match current filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->payables->hasPages())
            <div class="border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">{{ $this->payables->links() }}</div>
        @endif
    </flux:card>

    <flux:card class="p-6">
        <flux:heading size="lg">{{ __('Create payment run') }}</flux:heading>
        <flux:text class="mt-1 text-sm">{{ __('Select open payables above, or leave none selected and optionally filter by supplier or due date. Only open or partially paid items are included.') }}</flux:text>
        <form wire:submit="createPaymentRun" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <flux:input wire:model="runScheduledFor" :label="__('Scheduled date')" type="date" required />
            <flux:select wire:model="runPaymentMethod" :label="__('Payment method')">
                <flux:select.option value="">{{ __('Any method') }}</flux:select.option>
                @foreach (SupplierPaymentMethod::cases() as $method)
                    <flux:select.option :value="$method->value">{{ $method->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="runSupplierId" :label="__('Supplier (optional)')">
                <flux:select.option value="">{{ __('All suppliers') }}</flux:select.option>
                @foreach ($this->suppliers as $supplier)
                    <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="runDueDateTo" :label="__('Due on or before')" type="date" />
            <flux:input wire:model="runNotes" :label="__('Notes')" />
            <div class="sm:col-span-2 lg:col-span-5 flex gap-2">
                <flux:button type="submit" variant="primary">{{ __('Create run') }}</flux:button>
                <flux:button type="button" variant="ghost" wire:click="clearSelection">{{ __('Clear selected rows') }}</flux:button>
            </div>
        </form>
    </flux:card>

    <flux:card class="p-0 overflow-hidden">
        <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700"><flux:heading size="lg">{{ __('Recent payment runs') }}</flux:heading></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50"><tr><th class="px-6 py-3 text-start">{{ __('Reference') }}</th><th class="px-6 py-3 text-start">{{ __('Status') }}</th><th class="px-6 py-3 text-start">{{ __('Scheduled') }}</th><th class="px-6 py-3 text-end">{{ __('Proposed') }}</th><th class="px-6 py-3 text-end">{{ __('Executed') }}</th><th class="px-6 py-3 text-end"></th></tr></thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->recentPaymentRuns as $run)
                        <tr>
                            <td class="px-6 py-3 font-medium">{{ $run->reference_code }}</td>
                            <td class="px-6 py-3 capitalize">{{ $run->status->value }}</td>
                            <td class="px-6 py-3">{{ $run->scheduled_for->format('Y-m-d') }}</td>
                            <td class="px-6 py-3 text-end tabular-nums">{{ TenantMoney::format((float) $run->proposed_amount, null, 4) }}</td>
                            <td class="px-6 py-3 text-end tabular-nums">{{ TenantMoney::format((float) $run->executed_amount, null, 4) }}</td>
                            <td class="px-6 py-3 text-end"><flux:button size="sm" :href="route('accounting.payment-runs.show', ['id' => $run->id])" wire:navigate>{{ __('View') }}</flux:button></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-zinc-500">{{ __('No payment runs yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</div>
