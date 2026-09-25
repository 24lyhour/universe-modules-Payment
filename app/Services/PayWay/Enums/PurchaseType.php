<?php

namespace Modules\Payment\Services\PayWay\Enums;

enum PurchaseType: string
{
    case PURCHASE = 'purchase';
    case PRE_AUTH = 'pre_auth';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Purchase',
            self::PRE_AUTH => 'Pre-Authorization',
        };
    }
}
