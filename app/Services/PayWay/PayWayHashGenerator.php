<?php

namespace Modules\Payment\Services\PayWay;

use Modules\Payment\Services\PayWay\DTOs\PurchaseRequest;
use Modules\Payment\Services\PayWay\DTOs\QrRequest;

final class PayWayHashGenerator
{
    public function __construct(
        private PayWayConfig $config
    ) {}

    /**
     * Generate HMAC-SHA512 hash for PayWay API.
     */
    public function generate(string $data): string
    {
        return base64_encode(
            hash_hmac('sha512', $data, 
            $this->config->getApiKey(), true)
        );
    }

    /**
     * Generate hash for purchase request.
     */
    public function generatePurchaseHash(PurchaseRequest $request): string
    {
        $hashData = $request->reqTime
            . $this->config->getMerchantId()
            . $request->tranId
            . $request->amount
            . $request->getEncodedItems()
            . $request->shipping
            . $request->firstName
            . $request->lastName
            . $request->email
            . $request->phone
            . $request->type
            . $request->paymentOption
            . $request->getEncodedReturnUrl()
            . $request->cancelUrl
            . $request->getEncodedContinueSuccessUrl()
            . $request->returnDeeplink
            . $request->currency
            . $request->customFields
            . $request->returnParams
            . $request->payout
            . $request->lifetime;

        return $this->generate($hashData);
    }

    /**
     * Generate hash for QR request.
     */
    public function generateQrHash(QrRequest $request): string
    {
        $hashData = $request->reqTime
            . $this->config->getMerchantId()
            . $request->tranId
            . $request->amount
            . $request->getEncodedItems()
            . $request->firstName
            . $request->lastName
            . $request->email
            . $request->phone
            . $request->purchaseType
            . $request->paymentOption
            . $request->getEncodedCallbackUrl()
            . $request->returnDeeplink
            . $request->currency
            . $request->customFields
            . $request->returnParams
            . $request->payout
            . $request->lifetime
            . $request->qrImageTemplate;

        return $this->generate($hashData);
    }
}
