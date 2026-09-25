<?php

namespace Modules\Payment\Services\PayWay\DTOs;

use Modules\Payment\Services\PayWay\PayWayConfig;

final class PurchaseRequest
{
    public function __construct(
        public readonly string $reqTime,
        public readonly string $tranId,
        public readonly string $amount,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $phone,
        public readonly string $type,
        public readonly string $paymentOption,
        public readonly string $currency,
        public readonly string $returnUrl,
        public readonly string $returnDeeplink,
        public readonly string $cancelUrl,
        public readonly string $continueSuccessUrl,
        public readonly string $customFields,
        public readonly string $returnParams,
        public readonly string $shipping,
        public readonly string $payout,
        public readonly string $lifetime,
        public readonly ?array $items,
    ) {}

    public static function fromArray(array $params, PayWayConfig $config): self
    {
        self::validate($params);

        return new self(
            reqTime: gmdate('YmdHis'),
            tranId: $params['tran_id'],
            amount: $params['amount'],
            firstName: $params['firstname'] ?? '',
            lastName: $params['lastname'] ?? '',
            email: $params['email'] ?? '',
            phone: $params['phone'] ?? '',
            type: $params['type'] ?? 'purchase',
            paymentOption: $params['payment_option'] ?? 'abapay_khqr_deeplink',
            currency: $params['currency'] ?? 'USD',
            returnUrl: $config->getCallbackUrl(),
            returnDeeplink: $config->getReturnDeeplink(),
            cancelUrl: $params['cancel_url'] ?? '',
            continueSuccessUrl: $params['continue_success_url'] ?? '',
            customFields: '',
            returnParams: $params['return_params'] ?? '',
            shipping: $params['shipping'] ?? '',
            payout: '',
            lifetime: $params['lifetime'] ?? '',
            items: $params['items'] ?? null,
        );
    }

    public function getEncodedItems(): string
    {
        if (empty($this->items)) {
            return '';
        }

        return base64_encode(json_encode($this->items));
    }

    public function getEncodedReturnUrl(): string
    {
        return base64_encode($this->returnUrl);
    }

    public function getEncodedContinueSuccessUrl(): string
    {
        if (empty($this->continueSuccessUrl)) {
            return '';
        }

        return base64_encode($this->continueSuccessUrl);
    }

    public function toPayload(string $merchantId, string $hash): array
    {
        $payload = [
            'req_time' => $this->reqTime,
            'merchant_id' => $merchantId,
            'tran_id' => $this->tranId,
            'amount' => $this->amount,
            'hash' => $hash,
            'firstname' => $this->firstName,
            'lastname' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'type' => $this->type,
            'payment_option' => $this->paymentOption,
            'return_url' => $this->getEncodedReturnUrl(),
            'return_deeplink' => $this->returnDeeplink,
            'currency' => $this->currency,
        ];

        $this->addOptionalField($payload, 'items', $this->getEncodedItems());
        $this->addOptionalField($payload, 'shipping', $this->shipping);
        $this->addOptionalField($payload, 'cancel_url', $this->cancelUrl);
        $this->addOptionalField($payload, 'continue_success_url', $this->getEncodedContinueSuccessUrl());
        $this->addOptionalField($payload, 'return_params', $this->returnParams);
        $this->addOptionalField($payload, 'lifetime', $this->lifetime);

        return $payload;
    }

    private function addOptionalField(array &$payload, string $key, string $value): void
    {
        if (!empty($value)) {
            $payload[$key] = $value;
        }
    }

    /**
     * Validate required parameters.
     *
     * @throws \InvalidArgumentException
     */
    private static function validate(array $params): void
    {
        $required = ['tran_id', 'amount'];

        foreach ($required as $field) {
            if (empty($params[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!is_numeric($params['amount']) || $params['amount'] <= 0) {
            throw new \InvalidArgumentException('Amount must be a positive number');
        }
    }
}
