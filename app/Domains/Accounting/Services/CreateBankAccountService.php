<?php

namespace App\Domains\Accounting\Services;

use App\Enums\BankAccountStatus;
use App\Models\BankAccount;

class CreateBankAccountService
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
    public function execute(int $tenantId, array $data): BankAccount
    {
        return BankAccount::query()->create([
            'tenant_id' => $tenantId,
            'bank_name' => $data['bank_name'],
            'account_number' => $data['account_number'],
            'account_name' => $data['account_name'],
            'account_type' => $data['account_type'],
            'status' => $data['status'] ?? BankAccountStatus::Active->value,
        ]);
    }
}
