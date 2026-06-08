<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Support\OpenItemStatusResolver;
use App\Enums\SupplierAdvanceStatus;
use App\Models\AccountsPayable;
use App\Models\SupplierAdvance;
use App\Models\SupplierAdvanceApplication;
use Illuminate\Support\Facades\DB;

class ApplySupplierAdvancesToPayableService
{
    /**
     * @return list<SupplierAdvanceApplication>
     */
    public function execute(int $tenantId, int $accountsPayableId): array
    {
        return DB::transaction(function () use ($tenantId, $accountsPayableId): array {
            /** @var AccountsPayable $payable */
            $payable = AccountsPayable::query()
                ->where('tenant_id', $tenantId)
                ->with('goodsReceipt.purchaseOrder')
                ->whereKey($accountsPayableId)
                ->lockForUpdate()
                ->firstOrFail();

            $purchaseOrderId = $payable->goodsReceipt?->purchase_order_id;
            if ($purchaseOrderId === null) {
                return [];
            }

            $remainingPayable = OpenItemStatusResolver::remaining(
                (string) $payable->total_amount,
                (string) $payable->amount_paid,
            );

            if (bccomp($remainingPayable, '0', 4) !== 1) {
                return [];
            }

            $advances = SupplierAdvance::query()
                ->where('tenant_id', $tenantId)
                ->where('purchase_order_id', $purchaseOrderId)
                ->where('status', SupplierAdvanceStatus::Cleared)
                ->orderBy('paid_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $applications = [];

            foreach ($advances as $advance) {
                if (bccomp($remainingPayable, '0', 4) !== 1) {
                    break;
                }

                $advanceRemaining = $advance->remainingAmount();
                if (bccomp($advanceRemaining, '0', 4) !== 1) {
                    continue;
                }

                $applyAmount = bccomp($advanceRemaining, $remainingPayable, 4) === 1
                    ? $remainingPayable
                    : $advanceRemaining;

                $application = SupplierAdvanceApplication::query()->create([
                    'supplier_advance_id' => $advance->id,
                    'accounts_payable_id' => $payable->id,
                    'amount' => $applyAmount,
                    'applied_at' => now(),
                ]);

                $advance->amount_applied = bcadd((string) $advance->amount_applied, $applyAmount, 4);
                $advance->save();

                $newPaid = bcadd((string) $payable->amount_paid, $applyAmount, 4);
                $payable->amount_paid = $newPaid;
                $payable->status = OpenItemStatusResolver::fromAmounts((string) $payable->total_amount, $newPaid);
                $payable->save();

                $remainingPayable = bcsub($remainingPayable, $applyAmount, 4);
                $applications[] = $application;
            }

            return $applications;
        });
    }
}
