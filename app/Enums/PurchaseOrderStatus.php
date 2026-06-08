<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Confirmed = 'confirmed';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function isPrintable(): bool
    {
        return in_array($this, [self::Confirmed, self::PartiallyReceived], true);
    }
}
