<?php

namespace App\Domains\Accounting\Services;

use App\Models\BankAccount;

class UpdateBankAccountService
{
    /**
     * @param  array{
     *     bank_name: string,
     *     account_number: string,
     *     account_name: string,
     *     account_type: string,
     *     status?: string|null
     * }  $data
     */
    public function execute(BankAccount $bankAccount, array $data): BankAccount
    {
        $bankAccount->update([
            'bank_name' => $data['bank_name'],
            'account_number' => $data['account_number'],
            'account_name' => $data['account_name'],
            'account_type' => $data['account_type'],
            'status' => $data['status'] ?? $bankAccount->status->value,
        ]);

        return $bankAccount->fresh();
    }
}
