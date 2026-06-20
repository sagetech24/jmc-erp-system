<div class="flex items-center justify-end gap-2">
    @can('create', \App\Models\BankAccount::class)
        <flux:modal.trigger name="bank-account-form">
            <flux:button variant="primary" size="sm" icon="plus" class="cursor-pointer" wire:click="prepareCreate">
                {{ __('Add bank account') }}
            </flux:button>
        </flux:modal.trigger>
    @endcan

    <flux:modal
        name="bank-account-form"
        class="[scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
        <div class="flex flex-col gap-5 p-1">
            <div>
                <flux:heading size="lg">{{ $this->editingBankAccountId ? __('Edit bank account') : __('Add bank account') }}</flux:heading>
                <flux:text class="mt-1 text-sm">
                    @if ($this->editingBankAccountId)
                        {{ __('Update bank account details. Account number must be unique within your organization.') }}
                    @else
                        {{ __('Add a tenant bank account for use on PDC and payment transactions.') }}
                    @endif
                </flux:text>
            </div>

            <form wire:submit="saveBankAccount" class="flex flex-col gap-4">
                <flux:fieldset>
                    <flux:input wire:model="bank_name" :label="__('Bank Name')" type="text" placeholder="{{ __('e.g. BDO, BPI, Metrobank') }}" required autofocus />
                    <div class="grid grid-cols-2 gap-4 my-6">
                        <div class="flex flex-col gap-1">
                            <flux:label>{{ __('Account Number') }} <span class="text-red-500">*</span></flux:label>
                            <flux:input wire:model="account_number" type="text" placeholder="{{ __('Account number') }}" required />
                        </div>
                        <div class="flex flex-col gap-1">
                            <flux:label>{{ __('Account Name (Payee)') }} <span class="text-red-500">*</span></flux:label>
                            <flux:input wire:model="account_name" type="text" placeholder="{{ __('Registered payee name') }}" required />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1">
                            <flux:label>{{ __('Account Type') }} <span class="text-red-500">*</span></flux:label>
                            <flux:select wire:model="account_type" required>
                                @foreach (\App\Enums\BankAccountType::cases() as $case)
                                    <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <flux:label>{{ __('Status') }} <span class="text-red-500">*</span></flux:label>
                            <flux:select wire:model="status" required>
                                @foreach (\App\Enums\BankAccountStatus::cases() as $case)
                                    <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>
                </flux:fieldset>
                <div class="flex flex-col gap-2 pt-4 sm:flex-row sm:justify-between">
                    @if ($this->editingBankAccountId)
                        <flux:button
                            type="button"
                            variant="danger"
                            wire:click="deleteBankAccount"
                            wire:confirm="{{ __('Delete this bank account? This cannot be undone.') }}"
                            class="w-full cursor-pointer sm:w-auto"
                        >
                            {{ __('Delete') }}
                        </flux:button>
                    @endif
                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-end sm:ms-auto">
                        @if ($this->editingBankAccountId)
                            <flux:button type="button" variant="filled" wire:click="cancel" class="w-full cursor-pointer sm:w-auto">
                                {{ __('Cancel') }}
                            </flux:button>
                        @endif
                        <flux:button variant="primary" type="submit" class="w-full cursor-pointer sm:w-auto">
                            {{ $this->editingBankAccountId ? __('Update Bank Account') : __('Save Bank Account') }}
                        </flux:button>
                    </div>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
