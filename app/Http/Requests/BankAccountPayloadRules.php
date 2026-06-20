<?php

namespace App\Http\Requests;

use App\Enums\BankAccountStatus;
use App\Enums\BankAccountType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

final class BankAccountPayloadRules
{
    /**
     * @param  ?int  $ignoreBankAccountId  When updating, pass the id so `account_number` can stay unchanged.
     * @return array<string, list<string|ValidationRule>>
     */
    public static function rules(?int $ignoreBankAccountId = null): array
    {
        $tenantId = (int) session('current_tenant_id');

        $uniqueAccountNumber = Rule::unique('bank_accounts', 'account_number')
            ->where(fn ($query) => $query->where('tenant_id', $tenantId));

        if ($ignoreBankAccountId !== null) {
            $uniqueAccountNumber = $uniqueAccountNumber->ignore($ignoreBankAccountId);
        }

        return [
            'bank_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:64', $uniqueAccountNumber],
            'account_name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', Rule::enum(BankAccountType::class)],
            'status' => ['required', Rule::enum(BankAccountStatus::class)],
        ];
    }
}
