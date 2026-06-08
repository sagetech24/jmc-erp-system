<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Procurement\Support\PurchaseOrderTotalCalculator;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierAdvanceStatus;
use App\Enums\SupplierPaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\SupplierAdvance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordSupplierAdvanceService
{
    public function execute(
        int $tenantId,
        int $purchaseOrderId,
        string $amount,
        string $paymentMethod,
        string $paidAt,
        ?string $reference,
        ?string $notes,
        ?string $chequeNumber,
        ?string $chequeDate,
        ?string $chequeBank,
        ?string $chequePayee,
        ?int $recordedBy,
    ): SupplierAdvance {
        if (bccomp($amount, '0', 4) !== 1) {
            throw new InvalidArgumentException(__('Advance amount must be greater than zero.'));
        }

        $method = SupplierPaymentMethod::tryFrom($paymentMethod);
        if ($method === null) {
            throw new InvalidArgumentException(__('Invalid payment method.'));
        }

        if ($method === SupplierPaymentMethod::Pdc) {
            if ($chequeNumber === null || trim($chequeNumber) === '') {
                throw new InvalidArgumentException(__('Cheque number is required for PDC advances.'));
            }
            if ($chequeDate === null || trim($chequeDate) === '') {
                throw new InvalidArgumentException(__('Cheque date is required for PDC advances.'));
            }
            if ($chequeBank === null || trim($chequeBank) === '') {
                throw new InvalidArgumentException(__('Bank is required for PDC advances.'));
            }
        }

        return DB::transaction(function () use (
            $tenantId,
            $purchaseOrderId,
            $amount,
            $method,
            $paidAt,
            $reference,
            $notes,
            $chequeNumber,
            $chequeDate,
            $chequeBank,
            $chequePayee,
            $recordedBy,
        ): SupplierAdvance {
            /** @var PurchaseOrder $purchaseOrder */
            $purchaseOrder = PurchaseOrder::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($purchaseOrderId)
                ->lockForUpdate()
                ->with('lines')
                ->firstOrFail();

            if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::Confirmed, PurchaseOrderStatus::PartiallyReceived], true)) {
                throw new InvalidArgumentException(__('Advances can only be recorded on open purchase orders.'));
            }

            $orderTotal = PurchaseOrderTotalCalculator::calculate($purchaseOrder);
            if (bccomp($orderTotal, '0', 4) !== 1) {
                throw new InvalidArgumentException(__('Cannot record an advance on a purchase order with zero value.'));
            }

            $existingAdvances = SupplierAdvance::query()
                ->where('tenant_id', $tenantId)
                ->where('purchase_order_id', $purchaseOrderId)
                ->where('status', '!=', SupplierAdvanceStatus::Cancelled)
                ->sum('amount');

            $nextTotal = bcadd((string) $existingAdvances, $amount, 4);
            if (bccomp($nextTotal, $orderTotal, 4) === 1) {
                throw new InvalidArgumentException(
                    __('Total advances cannot exceed the purchase order value of :total.', [
                        'total' => $orderTotal,
                    ])
                );
            }

            $status = $this->resolveInitialStatus($method, $chequeDate);
            $clearedAt = $status === SupplierAdvanceStatus::Cleared ? $paidAt : null;

            return SupplierAdvance::query()->create([
                'tenant_id' => $tenantId,
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id' => $purchaseOrder->supplier_id,
                'amount' => $amount,
                'amount_applied' => '0',
                'payment_method' => $method,
                'status' => $status,
                'paid_at' => $paidAt,
                'cleared_at' => $clearedAt,
                'cheque_number' => $chequeNumber,
                'cheque_date' => $chequeDate,
                'cheque_bank' => $chequeBank,
                'cheque_payee' => $chequePayee,
                'reference' => $reference,
                'notes' => $notes,
                'recorded_by' => $recordedBy,
            ]);
        });
    }

    private function resolveInitialStatus(SupplierPaymentMethod $method, ?string $chequeDate): SupplierAdvanceStatus
    {
        if ($method !== SupplierPaymentMethod::Pdc) {
            return SupplierAdvanceStatus::Cleared;
        }

        if ($chequeDate === null || trim($chequeDate) === '') {
            return SupplierAdvanceStatus::Scheduled;
        }

        $chequeDay = Carbon::parse($chequeDate)->startOfDay();
        if ($chequeDay->lte(now()->startOfDay())) {
            return SupplierAdvanceStatus::Cleared;
        }

        return SupplierAdvanceStatus::Scheduled;
    }
}
