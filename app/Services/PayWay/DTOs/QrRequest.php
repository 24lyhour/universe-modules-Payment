<?php

namespace Modules\Payment\Services\PayWay\DTOs;

use Modules\Payment\Services\PayWay\PayWayConfig;

final class QrRequest
{
    public function __construct(
        public readonly string $reqTime,
        public readonly string $tranId,
        public readonly string $amount,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $phone,
        public readonly string $purchaseType,
        public readonly string $paymentOption,
        public readonly string $currency,
        public readonly string $callbackUrl,
        public readonly string $returnDeeplink,
        public readonly string $customFields,
        public readonly string $returnParams,
        public readonly string $payout,
        public readonly int $lifetime,
        public readonly string $qrImageTemplate,
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
            purchaseType: $params['purchase_type'] ?? 'purchase',
            paymentOption: $params['payment_option'] ?? 'abapay_khqr',
            currency: $params['currency'] ?? 'USD',
            callbackUrl: $config->getCallbackUrl(),
            returnDeeplink: '',
            customFields: '',
            returnParams: '',
            payout: '',
            lifetime: $params['lifetime'] ?? 6,
            qrImageTemplate: $params['qr_image_template'] ?? 'template4_color',
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

    public function getEncodedCallbackUrl(): string
    {
        return base64_encode($this->callbackUrl);
    }

    public function toPayload(string $merchantId, string $hash): array
    {
        $payload = [
            'req_time'          => $this->reqTime,
            'merchant_id'       => $merchantId,
            'tran_id'           => $this->tranId,
            'first_name'        => $this->firstName,
            'last_name'         => $this->lastName,
            'email'             => $this->email,
            'phone'             => $this->phone,
            'amount'            => $this->amount,
            'purchase_type'     => $this->purchaseType,
            'payment_option'    => $this->paymentOption,
            'currency'          => $this->currency,
            'callback_url'      => $this->getEncodedCallbackUrl(),
            'lifetime'          => $this->lifetime,
            'qr_image_template' => $this->qrImageTemplate,
            'hash'              => $hash,
        ];

        // Add optional fields only if they have values
        $this->addOptionalField($payload, 'return_deeplink', $this->returnDeeplink);
        $this->addOptionalField($payload, 'custom_fields', $this->customFields);
        $this->addOptionalField($payload, 'return_params', $this->returnParams);
        $this->addOptionalField($payload, 'payout', $this->payout);
        $this->addOptionalField($payload, 'items', $this->getEncodedItems());

        return $payload;
    }

    private function addOptionalField(array &$payload, string $key, string $value): void
    {
        if ($value !== '') {
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
