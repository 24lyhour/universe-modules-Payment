<?php

namespace Modules\Payment\Services\PayWay;

final class PayWayConfig
{
    public function __construct(
        private string $merchantId,
        private string $apiKey,
        private string $baseUrl,
        private string $callbackUrl,
        private string $returnDeeplinkIos = 'cylicon://payment-result',
        private string $returnDeeplinkAndroid = 'cylicon://payment-result',
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            merchantId: config('payment.payway.merchant_id', ''),
            apiKey: config('payment.payway.api_key', ''),
            baseUrl: config('payment.payway.base_url', ''),
            callbackUrl: config('payment.payway.callback_url', ''),
            returnDeeplinkIos: config('payment.payway.return_deeplink_ios', 'cylicon://payment-result'),
            returnDeeplinkAndroid: config('payment.payway.return_deeplink_android', 'cylicon://payment-result'),
        );
    }

    public function withOutletCredentials(string $merchantId, string $apiKey): self
    {
        return new self(
            merchantId: $merchantId,
            apiKey: $apiKey,
            baseUrl: $this->baseUrl,
            callbackUrl: $this->callbackUrl,
            returnDeeplinkIos: $this->returnDeeplinkIos,
            returnDeeplinkAndroid: $this->returnDeeplinkAndroid,
        );
    }

    public function getMerchantId(): string
    {
        return $this->merchantId;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getCallbackUrl(): string
    {
        return $this->callbackUrl;
    }

    public function getEncodedCallbackUrl(): string
    {
        return base64_encode($this->callbackUrl);
    }

    public function getReturnDeeplink(): string
    {
        return base64_encode(json_encode([
            'ios_scheme' => $this->returnDeeplinkIos,
            'android_scheme' => $this->returnDeeplinkAndroid,
        ]));
    }
}
