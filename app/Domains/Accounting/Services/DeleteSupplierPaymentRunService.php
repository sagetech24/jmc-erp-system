<?php

namespace App\Domains\Accounting\Services;

use App\Enums\SupplierPaymentRunStatus;
use App\Models\SupplierPaymentRun;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteSupplierPaymentRunService
{
    public function execute(int $tenantId, int $runId): void
    {
        DB::transaction(function () use ($tenantId, $runId): void {
            /** @var SupplierPaymentRun $run */
            $run = SupplierPaymentRun::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($runId)
                ->lockForUpdate()
                ->with('items')
                ->firstOrFail();

            if (! in_array($run->status, [SupplierPaymentRunStatus::Draft, SupplierPaymentRunStatus::Approved, SupplierPaymentRunStatus::Cancelled], true)) {
                throw new InvalidArgumentException(__('Only draft, approved, or cancelled payment runs can be deleted.'));
            }

            if ($run->items->contains(fn ($item) => $item->supplier_payment_id !== null)) {
                throw new InvalidArgumentException(__('Payment runs with executed payments cannot be deleted.'));
            }

            $run->delete();
        });
    }
}
