<?php

namespace App\Livewire\BankAccounts;

use App\Domains\Accounting\Services\CreateBankAccountService;
use App\Domains\Accounting\Services\DeleteBankAccountService;
use App\Domains\Accounting\Services\UpdateBankAccountService;
use App\Enums\BankAccountStatus;
use App\Enums\BankAccountType;
use App\Http\Requests\BankAccountPayloadRules;
use App\Models\BankAccount;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class BankAccountFormModal extends Component
{
    public string $bank_name = '';

    public string $account_number = '';

    public string $account_name = '';

    public string $account_type = '';

    public string $status = '';

    public ?int $editingBankAccountId = null;

    public function mount(): void
    {
        $this->account_type = BankAccountType::Checking->value;
        $this->status = BankAccountStatus::Active->value;
    }

    public function prepareCreate(): void
    {
        Gate::authorize('create', BankAccount::class);

        $this->editingBankAccountId = null;
        $this->reset('bank_name', 'account_number', 'account_name');
        $this->account_type = BankAccountType::Checking->value;
        $this->status = BankAccountStatus::Active->value;
        $this->resetValidation();
    }

    #[On('bank-account-form-open-edit')]
    public function openForEdit(int $bankAccountId): void
    {
        $tenantId = (int) session('current_tenant_id');

        $bankAccount = BankAccount::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($bankAccountId)
            ->firstOrFail();

        Gate::authorize('update', $bankAccount);

        $this->editingBankAccountId = $bankAccount->id;
        $this->bank_name = $bankAccount->bank_name;
        $this->account_number = $bankAccount->account_number;
        $this->account_name = $bankAccount->account_name;
        $this->account_type = $bankAccount->account_type->value;
        $this->status = $bankAccount->status->value;
        $this->resetValidation();

        $this->modal('bank-account-form')->show();
    }

    public function cancel(): void
    {
        $this->editingBankAccountId = null;
        $this->reset('bank_name', 'account_number', 'account_name');
        $this->account_type = BankAccountType::Checking->value;
        $this->status = BankAccountStatus::Active->value;
        $this->resetValidation();
        $this->modal('bank-account-form')->close();
    }

    public function saveBankAccount(CreateBankAccountService $create, UpdateBankAccountService $update): void
    {
        $tenantId = (int) session('current_tenant_id');

        if ($this->editingBankAccountId !== null) {
            $bankAccount = BankAccount::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($this->editingBankAccountId)
                ->firstOrFail();

            Gate::authorize('update', $bankAccount);

            $validated = $this->validate(BankAccountPayloadRules::rules($this->editingBankAccountId));
            $update->execute($bankAccount, $validated);

            $this->cancel();

            Flux::toast(variant: 'success', text: __('Bank account updated.'));
            $this->dispatch('bank-account-saved');

            return;
        }

        Gate::authorize('create', BankAccount::class);

        $validated = $this->validate(BankAccountPayloadRules::rules());

        $create->execute($tenantId, $validated);

        $this->editingBankAccountId = null;
        $this->reset('bank_name', 'account_number', 'account_name');
        $this->account_type = BankAccountType::Checking->value;
        $this->status = BankAccountStatus::Active->value;
        $this->resetValidation();

        $this->modal('bank-account-form')->close();

        Flux::toast(variant: 'success', text: __('Bank account added.'));
        $this->dispatch('bank-account-saved');
    }

    public function deleteBankAccount(DeleteBankAccountService $delete): void
    {
        if ($this->editingBankAccountId === null) {
            return;
        }

        $tenantId = (int) session('current_tenant_id');

        $bankAccount = BankAccount::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($this->editingBankAccountId)
            ->firstOrFail();

        Gate::authorize('delete', $bankAccount);

        $delete->execute($tenantId, $bankAccount->id);

        $this->cancel();

        Flux::toast(variant: 'success', text: __('Bank account deleted.'));
        $this->dispatch('bank-account-saved');
    }

    public function render()
    {
        return view('livewire.bank-accounts.bank-account-form-modal');
    }
}
