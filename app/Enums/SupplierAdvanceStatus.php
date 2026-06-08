<?php

namespace App\Enums;

enum SupplierAdvanceStatus: string
{
    case Scheduled = 'scheduled';
    case Cleared = 'cleared';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => __('Scheduled'),
            self::Cleared => __('Cleared'),
            self::Cancelled => __('Cancelled'),
        };
    }
}
