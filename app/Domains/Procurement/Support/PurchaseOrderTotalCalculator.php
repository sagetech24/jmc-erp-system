<?php

namespace App\Domains\Procurement\Support;

use App\Models\PurchaseOrder;

final class PurchaseOrderTotalCalculator
{
    public static function calculate(PurchaseOrder $purchaseOrder): string
    {
        $purchaseOrder->loadMissing('lines');

        $total = '0';

        foreach ($purchaseOrder->lines as $line) {
            $unitCost = $line->unit_cost !== null ? (string) $line->unit_cost : '0';
            $lineTotal = bcmul((string) $line->quantity_ordered, $unitCost, 4);
            $total = bcadd($total, $lineTotal, 4);
        }

        return $total;
    }
}
