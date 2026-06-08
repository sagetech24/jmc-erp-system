<?php

namespace App\Http\Requests;

use App\Enums\SupplierPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) session('current_tenant_id');

        return [
            'purchase_order_id' => [
                'required',
                'integer',
                Rule::exists('purchase_orders', 'id')->where('tenant_id', $tenantId),
            ],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
            'payment_method' => ['required', Rule::enum(SupplierPaymentMethod::class)],
            'paid_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'cheque_number' => ['nullable', 'string', 'max:64'],
            'cheque_date' => ['nullable', 'date'],
            'cheque_bank' => ['nullable', 'string', 'max:255'],
            'cheque_payee' => ['nullable', 'string', 'max:255'],
        ];
    }
}
