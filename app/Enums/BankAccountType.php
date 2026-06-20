<?php

namespace App\Enums;

enum BankAccountType: string
{
    case Savings = 'savings';
    case Checking = 'checking';

    public function label(): string
    {
        return match ($this) {
            self::Savings => __('Savings Account'),
            self::Checking => __('Checking Account'),
        };
    }
}
