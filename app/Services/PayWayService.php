<?php

namespace Modules\Payment\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayWayService
{
    protected string $merchantId;
    protected string $apiKey;
    protected string $baseUrl;
    protected string $callbackUrl;

    public function __construct()
    {
        $this->merchantId = config('payment.payway.merchant_id');
        $this->apiKey = config('payment.payway.api_key');
        $this->baseUrl = config('payment.payway.base_url');
        $this->callbackUrl = config('payment.payway.callback_url');
    }

    /**
     * Generate HMAC-SHA512 hash for PayWay API.
     */
    public function generateHash(string $data): string
    {
        return base64_encode(hash_hmac('sha512', $data, $this->apiKey, true));
    }

    /**
     * Create a purchase transaction with PayWay.
     *
     * Returns deeplink + QR for ABA PAY, or checkout URL for cards.
     */
    public function createPurchase(array $params): array
    {
        $reqTime = gmdate('YmdHis');
        $tranId = $params['tran_id'];
        $amount = $params['amount'];
        $firstName = $params['firstname'] ?? '';
        $lastName = $params['lastname'] ?? '';
        $email = $params['email'] ?? '';
        $phone = $params['phone'] ?? '';
        $type = $params['type'] ?? 'purchase';
        $paymentOption = $params['payment_option'] ?? 'abapay_khqr_deeplink';
        $currency = $params['currency'] ?? 'USD';
        $returnUrl = base64_encode($this->callbackUrl);
        $continueSuccessUrl = isset($params['continue_success_url'])
            ? base64_encode($params['continue_success_url'])
            : '';
        $cancelUrl = $params['cancel_url'] ?? '';
        $returnDeeplink = '';
        $customFields = '';
        $returnParams = $params['return_params'] ?? '';
        $shipping = $params['shipping'] ?? '';
        $payout = '';
        $lifetime = $params['lifetime'] ?? '';

        // Items as base64 JSON
        $items = '';
        if (!empty($params['items'])) {
            $items = base64_encode(json_encode($params['items']));
        }

        // Hash concatenation order (from PayWay docs)
        $hashData = $reqTime
            . $this->merchantId
            . $tranId
            . $amount
            . $items
            . $shipping
            . $firstName
            . $lastName
            . $email
            . $phone
            . $type
            . $paymentOption
            . $returnUrl
            . $cancelUrl
            . $continueSuccessUrl
            . $returnDeeplink
            . $currency
            . $customFields
            . $returnParams
            . $payout
            . $lifetime;

        $hash = $this->generateHash($hashData);

        $payload = [
            'req_time' => $reqTime,
            'merchant_id' => $this->merchantId,
            'tran_id' => $tranId,
            'amount' => $amount,
            'hash' => $hash,
            'firstname' => $firstName,
            'lastname' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'type' => $type,
            'payment_option' => $paymentOption,
            'return_url' => $returnUrl,
            'currency' => $currency,
        ];

        if ($items) $payload['items'] = $items;
        if ($shipping) $payload['shipping'] = $shipping;
        if ($cancelUrl) $payload['cancel_url'] = $cancelUrl;
        if ($continueSuccessUrl) $payload['continue_success_url'] = $continueSuccessUrl;
        if ($returnParams) $payload['return_params'] = $returnParams;
        if ($lifetime) $payload['lifetime'] = $lifetime;

        Log::info('PayWay: Creating purchase', ['tran_id' => $tranId, 'amount' => $amount]);

        try {
            $response = Http::asMultipart()
                ->post("{$this->baseUrl}/api/payment-gateway/v1/payments/purchase", $this->toMultipart($payload));

            $body = $response->json() ?? $response->body();

            Log::info('PayWay: Purchase response', [
                'status' => $response->status(),
                'body' => is_string($body) ? substr($body, 0, 500) : $body,
            ]);

            if ($response->successful() && is_array($body)) {
                return [
                    'success' => ($body['status']['code'] ?? '') === '00',
                    'data' => $body,
                ];
            }

            return [
                'success' => false,
                'error' => is_array($body) ? ($body['status']['message'] ?? 'Unknown error') : 'Invalid response',
                'data' => $body,
            ];
        } catch (\Exception $e) {
            Log::error('PayWay: Purchase failed', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate branded KHQR QR code via PayWay Generate QR API.
     */
    public function generateQr(array $params): array
    {
        $reqTime = gmdate('YmdHis');
        $tranId = $params['tran_id'];
        $amount = $params['amount'];
        $firstName = $params['firstname'] ?? '';
        $lastName = $params['lastname'] ?? '';
        $email = $params['email'] ?? '';
        $phone = $params['phone'] ?? '';
        $purchaseType = $params['purchase_type'] ?? 'purchase';
        $paymentOption = $params['payment_option'] ?? 'abapay_khqr';
        $currency = $params['currency'] ?? 'USD';
        $callbackUrl = base64_encode($this->callbackUrl);
        $returnDeeplink = '';
        $customFields = '';
        $returnParams = $params['return_params'] ?? '';
        $payout = '';
        $lifetime = $params['lifetime'] ?? '';
        $qrImageTemplate = $params['qr_image_template'] ?? 'template6_color';

        // Items as base64 JSON
        $items = '';
        if (!empty($params['items'])) {
            $items = base64_encode(json_encode($params['items']));
        }

        // Hash concatenation order (from PayWay QR API docs)
        $hashData = $reqTime
            . $this->merchantId
            . $tranId
            . $amount
            . $items
            . $firstName
            . $lastName
            . $email
            . $phone
            . $purchaseType
            . $paymentOption
            . $callbackUrl
            . $returnDeeplink
            . $currency
            . $customFields
            . $returnParams
            . $payout
            . $lifetime
            . $qrImageTemplate;

        $hash = $this->generateHash($hashData);

        $payload = [
            'req_time' => $reqTime,
            'merchant_id' => $this->merchantId,
            'tran_id' => $tranId,
            'amount' => $amount,
            'currency' => $currency,
            'payment_option' => $paymentOption,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'purchase_type' => $purchaseType,
            'callback_url' => $callbackUrl,
            'return_params' => $returnParams,
            'qr_image_template' => $qrImageTemplate,
            'hash' => $hash,
        ];

        if ($items) $payload['items'] = $items;
        if ($lifetime) $payload['lifetime'] = $lifetime;

        Log::info('PayWay: Generating QR', ['tran_id' => $tranId, 'amount' => $amount, 'template' => $qrImageTemplate]);

        try {
            $response = Http::post("{$this->baseUrl}/api/payment-gateway/v1/payments/generate-qr", $payload);

            $body = $response->json() ?? $response->body();

            Log::info('PayWay: QR response', [
                'status' => $response->status(),
                'body' => is_string($body) ? substr($body, 0, 500) : array_diff_key($body, ['qrImage' => true]),
            ]);

            if ($response->successful() && is_array($body)) {
                $code = $body['status']['code'] ?? '';
                return [
                    'success' => $code === '0' || $code === 0,
                    'data' => $body,
                ];
            }

            return [
                'success' => false,
                'error' => is_array($body) ? ($body['status']['message'] ?? 'Unknown error') : 'Invalid response',
                'data' => $body,
            ];
        } catch (\Exception $e) {
            Log::error('PayWay: Generate QR failed', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check transaction status.
     */
    public function checkTransaction(string $tranId): array
    {
        $reqTime = gmdate('YmdHis');

        $hashData = $reqTime . $this->merchantId . $tranId;
        $hash = $this->generateHash($hashData);

        try {
            $response = Http::post("{$this->baseUrl}/api/payment-gateway/v1/payments/check-transaction-2", [
                'req_time' => $reqTime,
                'merchant_id' => $this->merchantId,
                'tran_id' => $tranId,
                'hash' => $hash,
            ]);

            $body = $response->json();

            return [
                'success' => $response->successful(),
                'data' => $body,
            ];
        } catch (\Exception $e) {
            Log::error('PayWay: Check transaction failed', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify callback signature from PayWay webhook.
     */
    public function verifyCallback(array $payload, string $signature): bool
    {
        $sorted = $payload;
        ksort($sorted);

        $hashData = '';
        foreach ($sorted as $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $hashData .= $value;
        }

        $expectedSignature = $this->generateHash($hashData);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Generate a unique transaction ID for PayWay (max 20 chars).
     */
    public function generateTranId(int $orderId): string
    {
        return 'CYL' . $orderId . '-' . substr(uniqid(), -6);
    }

    /**
     * Convert associative array to multipart format for HTTP client.
     */
    protected function toMultipart(array $data): array
    {
        $multipart = [];
        foreach ($data as $key => $value) {
            $multipart[] = ['name' => $key, 'contents' => (string) $value];
        }
        return $multipart;
    }
}
