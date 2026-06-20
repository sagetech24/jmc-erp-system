<?php

namespace App\Domains\Accounting\Services;

use App\Models\BankAccount;

class DeleteBankAccountService
{
    public function execute(int $tenantId, int $bankAccountId): void
    {
        $bankAccount = BankAccount::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($bankAccountId)
            ->firstOrFail();

        $bankAccount->delete();
    }
}
