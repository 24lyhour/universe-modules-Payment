<?php

namespace Modules\Payment\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Outlet\Models\Outlet;
use Modules\Payment\Services\PayWay\PayWayConfig;
use Modules\Payment\Services\PayWay\PayWayHashGenerator;
use Modules\Payment\Services\PayWay\PayWayResponseHandler;
use Modules\Payment\Services\PayWay\DTOs\PurchaseRequest;
use Modules\Payment\Services\PayWay\DTOs\QrRequest;
use Modules\Payment\Services\PayWay\DTOs\PayWayResponse;

class PayWayService
{
    private PayWayConfig $config;
    private PayWayHashGenerator $hashGenerator;
    private PayWayResponseHandler $responseHandler;

    public function __construct(
        ?PayWayConfig $config = null,
        ?PayWayHashGenerator $hashGenerator = null,
        ?PayWayResponseHandler $responseHandler = null
    ) {
        $this->config = $config ?? PayWayConfig::fromConfig();
        $this->hashGenerator = $hashGenerator ?? new PayWayHashGenerator($this->config);
        $this->responseHandler = $responseHandler ?? new PayWayResponseHandler();
    }

    /**
     * Create a new instance with outlet-specific merchant credentials.
     * Returns a new instance to avoid mutating the singleton.
     */
    public function forOutlet(Outlet $outlet): self
    {
        if (!$outlet->hasPayWay()) {
            return $this;
        }

        $newConfig = $this->config->withOutletCredentials(
            $outlet->payway_merchant_id,
            $outlet->payway_api_key
        );

        return new self(
            config: $newConfig,
            hashGenerator: new PayWayHashGenerator($newConfig),
            responseHandler: $this->responseHandler
        );
    }

    /**
     * Create a purchase transaction with PayWay.
     */
    public function createPurchase(array $params): PayWayResponse
    {
        $request = PurchaseRequest::fromArray($params, $this->config);

        $hash = $this->hashGenerator->generatePurchaseHash($request);
        $payload = $request->toPayload($this->config->getMerchantId(), $hash);

        Log::info('PayWay: Creating purchase', [
            'tran_id' => $request->tranId,
            'amount' => $request->amount,
        ]);

        return $this->executeRequest(
            endpoint: '/api/payment-gateway/v1/payments/purchase',
            payload: $payload,
            useMultipart: true,
            context: 'Purchase'
        );
    }

    /**
     * Generate branded KHQR QR code via PayWay Generate QR API.
     */
    public function generateQr(array $params): PayWayResponse
    {
        $request = QrRequest::fromArray($params, $this->config);

        $hash = $this->hashGenerator->generateQrHash($request);
        $payload = $request->toPayload($this->config->getMerchantId(), $hash);

        Log::info('PayWay: Generating QR', [
            'tran_id' => $request->tranId,
            'amount' => $request->amount,
            'template' => $request->qrImageTemplate,
        ]);

        return $this->executeRequest(
            endpoint: '/api/payment-gateway/v1/payments/generate-qr',
            payload: $payload,
            useMultipart: false,
            context: 'Generate QR',
            successCodes: ['0', 0]
        );
    }

    /**
     * Check transaction status.
     */
    public function checkTransaction(string $tranId): PayWayResponse
    {
        $reqTime = $this->generateRequestTime();
        $hash = $this->hashGenerator->generate($reqTime . $this->config->getMerchantId() . $tranId);

        $payload = [
            'req_time' => $reqTime,
            'merchant_id' => $this->config->getMerchantId(),
            'tran_id' => $tranId,
            'hash' => $hash,
        ];

        return $this->executeRequest(
            endpoint: '/api/payment-gateway/v1/payments/check-transaction-2',
            payload: $payload,
            useMultipart: true,
            context: 'Check transaction'
        );
    }

    /**
     * Verify callback signature from PayWay webhook.
     */
    public function verifyCallback(array $payload, string $signature): bool
    {
        $hashData = $this->buildCallbackHashData($payload);
        $expectedSignature = $this->hashGenerator->generate($hashData);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Generate a unique transaction ID for PayWay (max 20 chars).
     * Format: {PREFIX}{ORDER_ID}-{UNIQUE_6} = max 3 + 6 + 1 + 6 = 16 chars
     */
    public function generateTranId(int $orderId): string
    {
        $prefix = config('payment.payway.tran_id_prefix', 'CYL');
        $uniquePart = substr(uniqid(), -6);

        // Ensure order ID doesn't make the total exceed 20 chars
        // Max: 3 (prefix) + 1 (-) + 6 (unique) = 10, leaving 10 for orderId
        $maxOrderIdLength = 20 - strlen($prefix) - 1 - 6;
        $orderIdStr = (string) $orderId;

        if (strlen($orderIdStr) > $maxOrderIdLength) {
            $orderIdStr = substr($orderIdStr, -$maxOrderIdLength);
        }

        return "{$prefix}{$orderIdStr}-{$uniquePart}";
    }

    /**
     * Generate hash for external use.
     */
    public function generateHash(string $data): string
    {
        return $this->hashGenerator->generate($data);
    }

    /**
     * Get current merchant ID.
     */
    public function getMerchantId(): string
    {
        return $this->config->getMerchantId();
    }

    /**
     * Get current API key.
     */
    public function getApiKey(): string
    {
        return $this->config->getApiKey();
    }

    /**
     * Get current base URL.
     */
    public function getBaseUrl(): string
    {
        return $this->config->getBaseUrl();
    }

    /**
     * Get current callback URL.
     */
    public function getCallbackUrl(): string
    {
        return $this->config->getCallbackUrl();
    }

    /**
     * Get the config object.
     */
    public function getConfig(): PayWayConfig
    {
        return $this->config;
    }

    private const HTTP_TIMEOUT_SECONDS = 30;

    /**
     * Execute HTTP request to PayWay API.
     */
    private function executeRequest(
        string $endpoint,
        array $payload,
        bool $useMultipart,
        string $context,
        array $successCodes = ['00']
    ): PayWayResponse {
        try {
            $url = $this->config->getBaseUrl() . $endpoint;

            $http = Http::timeout(self::HTTP_TIMEOUT_SECONDS)
                ->connectTimeout(10);

            $response = $useMultipart
                ? $http->asMultipart()->post($url, $this->toMultipart($payload))
                : $http->post($url, $payload);

            return $this->responseHandler->handle($response, $context, $successCodes);
        } catch (\Exception $e) {
            Log::error("PayWay: {$context} failed", ['error' => $e->getMessage()]);

            return PayWayResponse::error($e->getMessage());
        }
    }

    /**
     * Build hash data from callback payload.
     */
    private function buildCallbackHashData(array $payload): string
    {
        $sorted = $payload;
        ksort($sorted);

        $hashData = '';
        foreach ($sorted as $value) {
            $hashData .= is_array($value) ? json_encode($value) : $value;
        }

        return $hashData;
    }

    /**
     * Generate request timestamp in PayWay format.
     */
    private function generateRequestTime(): string
    {
        return gmdate('YmdHis');
    }

    /**
     * Convert associative array to multipart format.
     */
    private function toMultipart(array $data): array
    {
        return array_map(
            fn($key, $value) => ['name' => $key, 'contents' => (string) $value],
            array_keys($data),
            array_values($data)
        );
    }
}
