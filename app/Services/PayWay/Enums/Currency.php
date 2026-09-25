<?php

namespace Modules\Payment\Services\PayWay\Enums;

enum Currency: string
{
    case USD = 'USD';
    case KHR = 'KHR';

    public function symbol(): string
    {
        return match ($this) {
            self::USD => '$',
            self::KHR => '៛',
        };
    }

    public function decimals(): int
    {
        return match ($this) {
            self::USD => 2,
            self::KHR => 0,
        };
    }
}
