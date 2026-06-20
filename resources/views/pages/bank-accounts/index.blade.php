<?php

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app', ['title' => 'Bank Accounts'])]
#[Title('Bank Accounts')]
class extends Component {
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', BankAccount::class);
    }

    #[On('bank-account-saved')]
    public function refreshAfterBankAccountSave(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openBankAccountEditModal(int $bankAccountId): void
    {
        $this->dispatch('bank-account-form-open-edit', bankAccountId: $bankAccountId);
    }

    public function getBankAccountsProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return BankAccount::query()
            ->where('tenant_id', $tenantId)
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(function (Builder $nested) use ($term): void {
                    $nested->where('bank_name', 'like', $term)
                        ->orWhere('account_number', 'like', $term)
                        ->orWhere('account_name', 'like', $term);
                });
            })
            ->orderBy('bank_name')
            ->orderBy('account_number')
            ->paginate(12)
            ->withPath(route('bank-accounts.index', absolute: false))
            ->withQueryString();
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Bank Accounts') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Manage tenant bank accounts used for PDC and payment transactions.') }}</flux:text>
        </div>
        <livewire:bank-accounts.bank-account-form-modal />
    </div>

    <div class="min-w-0 w-full">
        <flux:card class="flex flex-col overflow-hidden p-0 bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40">
            <div class="border-b border-zinc-200 px-6 py-5 dark:border-white/10">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <flux:heading size="lg">{{ __('Bank Accounts Master List') }}</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ __('All bank accounts recorded for your organization.') }}</flux:text>
                    </div>
                    <div class="w-full sm:max-w-xs shrink-0">
                        <flux:input
                            type="search"
                            wire:model.live.debounce.400ms="search"
                            placeholder="{{ __('Bank name, account number, payee…') }}"
                        />
                    </div>
                </div>
            </div>

            @if ($this->bankAccounts->isEmpty())
                <div class="p-6">
                    @if (trim($this->search) !== '')
                        <flux:callout icon="magnifying-glass" color="zinc" inline :heading="__('No matching bank accounts')" :text="__('Try another term or clear the search box.')" />
                    @else
                        <flux:callout icon="building-library" color="zinc" inline :heading="__('No bank accounts yet')" :text="__('Add a bank account with the Add bank account button above.')" />
                    @endif
                </div>
            @else
                <flux:table>
                    <flux:table.columns sticky class="bg-neutral-200 dark:bg-neutral-600">
                        <flux:table.column class="px-6!">{{ __('Bank Name') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Account Number') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Account Name (Payee)') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Account Type') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Status') }}</flux:table.column>
                        <flux:table.column align="end" class="w-0 whitespace-nowrap px-6!">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->bankAccounts as $bankAccount)
                            <flux:table.row :key="$bankAccount->id">
                                <flux:table.cell variant="strong" class="px-6!">{{ $bankAccount->bank_name }}</flux:table.cell>
                                <flux:table.cell class="px-6! font-mono text-sm tabular-nums">{{ $bankAccount->account_number }}</flux:table.cell>
                                <flux:table.cell class="px-6!">{{ $bankAccount->account_name }}</flux:table.cell>
                                <flux:table.cell class="px-6!">{{ $bankAccount->account_type->label() }}</flux:table.cell>
                                <flux:table.cell class="px-6!">{{ $bankAccount->status->label() }}</flux:table.cell>
                                <flux:table.cell align="end" class="px-6!">
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="primary"
                                        wire:click="openBankAccountEditModal({{ $bankAccount->id }})"
                                        inset="top bottom"
                                        class="border border-zinc-200 dark:border-white/40 cursor-pointer text-xs! p-1! px-2!"
                                    >
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>

        @if (! $this->bankAccounts->isEmpty() && $this->bankAccounts->hasPages())
            <div class="mt-4 flex justify-between px-1 sm:px-0 items-center gap-4">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 w-full">
                    {{ __('Showing') }} {{ $this->bankAccounts->firstItem() }} {{ __('to') }} {{ $this->bankAccounts->lastItem() }} {{ __('of') }} {{ $this->bankAccounts->total() }} {{ __('entries') }}
                </flux:text>
                {{ $this->bankAccounts->links('vendor.pagination.numbers-only') }}
            </div>
        @endif
    </div>
</div>
