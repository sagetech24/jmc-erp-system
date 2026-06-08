<?php

namespace App\Domains\Accounting\Services;

use App\Enums\AccountingOpenItemStatus;
use App\Enums\SupplierAdvanceStatus;
use App\Enums\SupplierPaymentMethod;
use App\Models\AccountsPayable;
use App\Models\SupplierAdvance;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClearSupplierAdvancePdcService
{
    public function __construct(
        private readonly ApplySupplierAdvancesToPayableService $applyAdvances,
    ) {}

    public function execute(int $tenantId, int $advanceId, ?string $clearedAt = null): SupplierAdvance
    {
        return DB::transaction(function () use ($tenantId, $advanceId, $clearedAt): SupplierAdvance {
            /** @var SupplierAdvance $advance */
            $advance = SupplierAdvance::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($advanceId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($advance->payment_method !== SupplierPaymentMethod::Pdc) {
                throw new InvalidArgumentException(__('Only PDC advances can be cleared through this action.'));
            }

            if ($advance->status !== SupplierAdvanceStatus::Scheduled) {
                throw new InvalidArgumentException(__('Only scheduled PDC advances can be cleared.'));
            }

            $advance->status = SupplierAdvanceStatus::Cleared;
            $advance->cleared_at = $clearedAt ?? now()->toDateTimeString();
            $advance->save();

            $payables = AccountsPayable::query()
                ->where('tenant_id', $tenantId)
                ->whereHas('goodsReceipt', fn ($q) => $q->where('purchase_order_id', $advance->purchase_order_id))
                ->whereIn('status', [AccountingOpenItemStatus::Open, AccountingOpenItemStatus::Partial])
                ->orderBy('posted_at')
                ->pluck('id');

            foreach ($payables as $payableId) {
                $this->applyAdvances->execute($tenantId, (int) $payableId);
            }

            return $advance->fresh(['purchaseOrder', 'supplier', 'recordedByUser']);
        });
    }
}
