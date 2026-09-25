<?php

namespace Modules\Payment\Services\PayWay\Enums;

enum PaymentOption: string
{
    case ABAPAY_KHQR = 'abapay_khqr';
    case ABAPAY_KHQR_DEEPLINK = 'abapay_khqr_deeplink';
    case CARDS  = 'cards';
    case ABAPAY = 'abapay';
    case BAKONG = 'bakong';

    public function label(): string
    {
        return match ($this) {
            self::ABAPAY_KHQR          => 'ABA PAY KHQR',
            self::ABAPAY_KHQR_DEEPLINK => 'ABA PAY KHQR Deeplink',
            self::CARDS  => 'Credit/Debit Cards',
            self::ABAPAY => 'ABA PAY',
            self::BAKONG => 'Bakong',
        };
    }

    public function isQrBased(): bool
    {
        return in_array($this, [
            self::ABAPAY_KHQR,
            self::ABAPAY_KHQR_DEEPLINK,
            self::BAKONG,
        ]);
    }
}
