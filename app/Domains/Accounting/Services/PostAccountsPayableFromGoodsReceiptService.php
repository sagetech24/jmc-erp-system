<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Support\OpenItemStatusResolver;
use App\Enums\GoodsReceiptStatus;
use App\Models\AccountsPayable;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PostAccountsPayableFromGoodsReceiptService
{
    public function __construct(
        private readonly ApplySupplierAdvancesToPayableService $applyAdvances,
    ) {}

    public function execute(int $tenantId, int $goodsReceiptId): AccountsPayable
    {
        return DB::transaction(function () use ($tenantId, $goodsReceiptId): AccountsPayable {
        $receipt = GoodsReceipt::query()
            ->where('tenant_id', $tenantId)
            ->with(['lines.purchaseOrderLine', 'purchaseOrder'])
            ->whereKey($goodsReceiptId)
            ->firstOrFail();

        if ($receipt->status !== GoodsReceiptStatus::Posted) {
            throw new InvalidArgumentException(__('Only posted goods receipts can be posted to accounts payable.'));
        }

        if ($receipt->accountsPayable()->exists()) {
            throw new InvalidArgumentException(__('Accounts payable already exists for this receipt.'));
        }

        $supplierId = $receipt->purchaseOrder->supplier_id;

        $total = '0';
        /** @var GoodsReceiptLine $line */
        foreach ($receipt->lines as $line) {
            $poLine = $line->purchaseOrderLine;
            $unitCost = $line->unit_cost !== null
                ? (string) $line->unit_cost
                : ($poLine->unit_cost !== null ? (string) $poLine->unit_cost : '0');
            $lineTotal = bcmul((string) $line->quantity_received, $unitCost, 4);
            $total = bcadd($total, $lineTotal, 4);
        }

        $payable = AccountsPayable::query()->create([
            'tenant_id' => $tenantId,
            'goods_receipt_id' => $receipt->id,
            'supplier_id' => $supplierId,
            'invoice_number' => $receipt->supplier_invoice_reference,
            'invoice_date' => $receipt->received_at->toDateString(),
            'due_date' => $receipt->received_at->copy()->addDays(30)->toDateString(),
            'payment_terms_days' => 30,
            'priority' => 3,
            'total_amount' => $total,
            'amount_paid' => '0',
            'status' => OpenItemStatusResolver::fromAmounts($total, '0'),
            'posted_at' => $receipt->received_at->toDateTimeString(),
        ]);

        $this->applyAdvances->execute($tenantId, $payable->id);

        return $payable->fresh();
        });
    }
}
