<?php

use App\Domains\Accounting\Services\RecordSupplierPaymentService;
use App\Enums\AccountingOpenItemStatus;
use App\Enums\SupplierPaymentMethod;
use App\Http\Requests\StoreSupplierPaymentRequest;
use App\Models\AccountsPayable;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Support\TenantMoney;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Supplier payment'])]
#[Title('Supplier payment')]
class extends Component {
    public string $supplier_id = '';

    public string $amount = '';

    public string $payment_method = '';

    public string $paid_at = '';

    public string $reference = '';

    public string $notes = '';

    public string $cheque_number = '';

    public string $cheque_date = '';

    public string $cheque_bank = '';

    public string $cheque_payee = '';

    public string $bank_name = '';

    public string $bank_account_number = '';

    public string $bank_reference = '';

    public string $digital_provider = '';

    public string $digital_account = '';

    public string $digital_transaction_id = '';

    /** @var array<int|string, string> */
    public array $allocationAmounts = [];

    public function mount(): void
    {
        Gate::authorize('create', SupplierPayment::class);
        $this->payment_method = SupplierPaymentMethod::Cash->value;
        $this->paid_at = Carbon::now()->format('Y-m-d\TH:i');

        $prefill = request()->query('supplier_id');
        if ($prefill !== null && $prefill !== '') {
            $tenantId = (int) session('current_tenant_id');
            $exists = Supplier::query()
                ->where('tenant_id', $tenantId)
                ->whereKey((int) $prefill)
                ->exists();
            if ($exists) {
                $this->supplier_id = (string) (int) $prefill;
            }
        }
    }

    public function updatedSupplierId(): void
    {
        $this->allocationAmounts = [];
    }

    public function updatedPaymentMethod(): void
    {
        $this->resetPaymentMethodFields();
    }

    private function resetPaymentMethodFields(): void
    {
        $this->cheque_number = '';
        $this->cheque_date = '';
        $this->cheque_bank = '';
        $this->cheque_payee = '';
        $this->bank_name = '';
        $this->bank_account_number = '';
        $this->bank_reference = '';
        $this->digital_provider = '';
        $this->digital_account = '';
        $this->digital_transaction_id = '';
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentMethodDetailRules(): array
    {
        return match ($this->payment_method) {
            SupplierPaymentMethod::Pdc->value => [
                'cheque_number' => ['required', 'string', 'max:64'],
                'cheque_date' => ['required', 'date'],
                'cheque_bank' => ['required', 'string', 'max:255'],
                'cheque_payee' => ['nullable', 'string', 'max:255'],
            ],
            SupplierPaymentMethod::BankTransfer->value => [
                'bank_name' => ['required', 'string', 'max:255'],
                'bank_account_number' => ['nullable', 'string', 'max:64'],
                'bank_reference' => ['required', 'string', 'max:255'],
            ],
            SupplierPaymentMethod::DigitalPayment->value => [
                'digital_provider' => ['required', 'string', 'max:255'],
                'digital_account' => ['required', 'string', 'max:255'],
                'digital_transaction_id' => ['required', 'string', 'max:255'],
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentMethodDetailPayload(): array
    {
        return match ($this->payment_method) {
            SupplierPaymentMethod::Pdc->value => [
                'cheque_number' => $this->cheque_number,
                'cheque_date' => $this->cheque_date,
                'cheque_bank' => $this->cheque_bank,
                'cheque_payee' => $this->cheque_payee !== '' ? $this->cheque_payee : null,
            ],
            SupplierPaymentMethod::BankTransfer->value => [
                'bank_name' => $this->bank_name,
                'bank_account_number' => $this->bank_account_number !== '' ? $this->bank_account_number : null,
                'bank_reference' => $this->bank_reference,
            ],
            SupplierPaymentMethod::DigitalPayment->value => [
                'digital_provider' => $this->digital_provider,
                'digital_account' => $this->digital_account,
                'digital_transaction_id' => $this->digital_transaction_id,
            ],
            default => [],
        };
    }

    private function composeNotesWithPaymentDetails(): ?string
    {
        $userNotes = trim($this->notes);
        $detailLines = match ($this->payment_method) {
            SupplierPaymentMethod::Pdc->value => array_filter([
                __('Cheque number: :value', ['value' => $this->cheque_number]),
                __('Cheque date: :value', ['value' => $this->cheque_date]),
                __('Bank: :value', ['value' => $this->cheque_bank]),
                $this->cheque_payee !== '' ? __('Payee: :value', ['value' => $this->cheque_payee]) : null,
            ]),
            SupplierPaymentMethod::BankTransfer->value => array_filter([
                __('Bank: :value', ['value' => $this->bank_name]),
                $this->bank_account_number !== '' ? __('Account number: :value', ['value' => $this->bank_account_number]) : null,
                __('Transfer reference: :value', ['value' => $this->bank_reference]),
            ]),
            SupplierPaymentMethod::DigitalPayment->value => [
                __('Provider: :value', ['value' => $this->digital_provider]),
                __('Account: :value', ['value' => $this->digital_account]),
                __('Transaction ID: :value', ['value' => $this->digital_transaction_id]),
            ],
            default => [],
        };

        if ($detailLines === []) {
            return $userNotes !== '' ? $userNotes : null;
        }

        $detailsBlock = implode("\n", $detailLines);

        return $userNotes !== ''
            ? $userNotes."\n\n".$detailsBlock
            : $detailsBlock;
    }

    private function resolveReference(): ?string
    {
        if ($this->reference !== '') {
            return $this->reference;
        }

        return match ($this->payment_method) {
            SupplierPaymentMethod::Pdc->value => $this->cheque_number !== '' ? $this->cheque_number : null,
            SupplierPaymentMethod::BankTransfer->value => $this->bank_reference !== '' ? $this->bank_reference : null,
            SupplierPaymentMethod::DigitalPayment->value => $this->digital_transaction_id !== '' ? $this->digital_transaction_id : null,
            default => null,
        };
    }

    public function getSuppliersProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return Supplier::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();
    }

    public function getOpenPayablesProperty()
    {
        if ($this->supplier_id === '' || $this->supplier_id === '0') {
            return collect();
        }

        $tenantId = (int) session('current_tenant_id');

        return AccountsPayable::query()
            ->where('tenant_id', $tenantId)
            ->where('supplier_id', (int) $this->supplier_id)
            ->whereIn('status', [AccountingOpenItemStatus::Open, AccountingOpenItemStatus::Partial])
            ->orderBy('posted_at')
            ->get();
    }

    public function recordPayment(RecordSupplierPaymentService $service): void
    {
        Gate::authorize('create', SupplierPayment::class);

        $allocations = [];
        foreach ($this->allocationAmounts as $apId => $amt) {
            $amt = trim((string) $amt);
            if ($amt === '' || bccomp($amt, '0', 4) !== 1) {
                continue;
            }
            $allocations[] = [
                'accounts_payable_id' => (int) $apId,
                'amount' => $amt,
            ];
        }

        $paidAt = $this->paid_at !== ''
            ? Carbon::parse($this->paid_at)->toDateTimeString()
            : now()->toDateTimeString();

        $payload = [
            'supplier_id' => $this->supplier_id,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'paid_at' => $paidAt,
            'reference' => $this->resolveReference(),
            'notes' => $this->composeNotesWithPaymentDetails(),
            'allocations' => $allocations,
            ...$this->paymentMethodDetailPayload(),
        ];

        Validator::make(
            $payload,
            [
                ...(new StoreSupplierPaymentRequest)->rules(),
                ...$this->paymentMethodDetailRules(),
            ],
        )->validate();

        try {
            $service->execute(
                (int) session('current_tenant_id'),
                (int) $payload['supplier_id'],
                (string) $payload['amount'],
                $paidAt,
                $payload['reference'],
                $payload['notes'],
                (string) $payload['payment_method'],
                $allocations,
            );
            Flux::toast(variant: 'success', text: __('Supplier payment recorded.'));
            $this->redirect(route('accounting.payables.index', absolute: false), navigate: true);
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Record supplier payment') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Allocate the payment to one or more open payables for the supplier. Total allocations must match the payment amount.') }}</flux:text>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <form wire:submit="recordPayment" class="flex flex-col gap-6">
            <flux:select
                wire:model.live="supplier_id"
                :label="__('Supplier')"
                :placeholder="__('Choose a supplier…')"
                required
            >
                @foreach ($this->suppliers as $supplier)
                    <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input
                wire:model="amount"
                :label="__('Payment amount')"
                type="text"
                inputmode="decimal"
                required
            />

            <flux:select wire:model.live="payment_method" :label="__('Payment method')" required>
                @foreach (SupplierPaymentMethod::cases() as $method)
                    <flux:select.option :value="$method->value">{{ $method->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($payment_method === SupplierPaymentMethod::Pdc->value)
                <div class="flex flex-col gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <flux:heading size="sm">{{ __('Cheque details') }}</flux:heading>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="cheque_number" :label="__('Cheque number')" type="text" required />
                        <flux:input wire:model="cheque_date" :label="__('Cheque date')" type="date" required />
                        <flux:input wire:model="cheque_bank" :label="__('Bank')" type="text" required class="sm:col-span-2" />
                        <flux:input wire:model="cheque_payee" :label="__('Payee (optional)')" type="text" class="sm:col-span-2" />
                    </div>
                </div>
            @elseif ($payment_method === SupplierPaymentMethod::BankTransfer->value)
                <div class="flex flex-col gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <flux:heading size="sm">{{ __('Bank transfer details') }}</flux:heading>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="bank_name" :label="__('Bank name')" type="text" required class="sm:col-span-2" />
                        <flux:input wire:model="bank_account_number" :label="__('Account number (optional)')" type="text" />
                        <flux:input wire:model="bank_reference" :label="__('Transfer reference')" type="text" required />
                    </div>
                </div>
            @elseif ($payment_method === SupplierPaymentMethod::DigitalPayment->value)
                <div class="flex flex-col gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <flux:heading size="sm">{{ __('Digital payment details') }}</flux:heading>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="digital_provider" :label="__('Provider')" type="text" :placeholder="__('e.g. GCash, Maya, PayPal')" required />
                        <flux:input wire:model="digital_account" :label="__('Account / mobile number')" type="text" required />
                        <flux:input wire:model="digital_transaction_id" :label="__('Transaction ID')" type="text" required class="sm:col-span-2" />
                    </div>
                </div>
            @endif

            <flux:input wire:model="paid_at" :label="__('Paid at')" type="datetime-local" required />

            <flux:input wire:model="reference" :label="__('Reference (optional)')" type="text" />

            <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />

            @if ($this->openPayables->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="mb-2">{{ __('Invoices from this supplier') }}</flux:heading>
                    <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                                <tr>
                                    <th class="px-4 py-2 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Sales Invoice') }}</th>
                                    <th class="px-4 py-2 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Remaining') }}</th>
                                    <th class="px-4 py-2 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach ($this->openPayables as $ap)
                                    @php
                                        $rem = \App\Domains\Accounting\Support\OpenItemStatusResolver::remaining(
                                            (string) $ap->total_amount,
                                            (string) $ap->amount_paid,
                                        );
                                    @endphp
                                    <tr wire:key="alloc-ap-{{ $ap->id }}">
                                        <td class="px-4 py-2 font-medium text-zinc-900 dark:text-zinc-100">{{ $ap->invoice_number }}</td>
                                        <td class="px-4 py-2 text-end tabular-nums text-zinc-600 dark:text-zinc-400">{{ TenantMoney::format((float) $rem, null, 4) }}</td>
                                        <td class="px-4 py-2 text-end">
                                            <flux:input
                                                class="max-w-[10rem] ms-auto"
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
            @elseif ($this->supplier_id !== '' && $this->supplier_id !== '0')
                <flux:text class="text-zinc-500">{{ __('No open payables for this supplier.') }}</flux:text>
            @endif

            <div class="flex flex-wrap gap-3">
                <flux:button variant="primary" type="submit">{{ __('Save payment') }}</flux:button>
                <flux:button :href="route('accounting.payables.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>
</div>
