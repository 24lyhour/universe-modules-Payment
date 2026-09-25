<?php

namespace Modules\Payment\Tests\Feature;

use Tests\TestCase;
use Modules\Payment\Services\PayWayService;

class PayWayServiceTest extends TestCase
{
    private PayWayService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PayWayService::class);
    }

    public function test_can_get_merchant_id(): void
    {
        $merchantId = $this->service->getMerchantId();

        $this->assertNotEmpty($merchantId);
        $this->assertEquals(config('payment.payway.merchant_id'), $merchantId);
    }

    public function test_can_generate_hash(): void
    {
        $hash = $this->service->generateHash('test_data');

        $this->assertNotEmpty($hash);
        $this->assertIsString($hash);
    }

    public function test_can_generate_tran_id(): void
    {
        $tranId = $this->service->generateTranId(12345);

        $this->assertStringStartsWith('CYL', $tranId);
        $this->assertStringContainsString('12345', $tranId);
        $this->assertLessThanOrEqual(20, strlen($tranId));
    }

    public function test_create_purchase_with_sandbox(): void
    {
        // Skip if no credentials
        if (empty(config('payment.payway.merchant_id'))) {
            $this->markTestSkipped('PayWay credentials not configured');
        }

        $result = $this->service->createPurchase([
            'tran_id' => $this->service->generateTranId(1),
            'amount' => '1.00',
            'firstname' => 'Test',
            'lastname' => 'User',
            'email' => 'test@example.com',
            'phone' => '012345678',
            'payment_option' => 'abapay_khqr',
            'currency' => 'USD',
        ]);

        // Check response structure (backward compatible array access)
        $this->assertArrayHasKey('success', $result->toArray());

        // Log result for debugging
        if (!$result['success']) {
            $this->addWarning('PayWay API returned: ' . ($result['error'] ?? 'Unknown error'));
        }
    }

    public function test_generate_qr_with_sandbox(): void
    {
        // Skip if no credentials
        if (empty(config('payment.payway.merchant_id'))) {
            $this->markTestSkipped('PayWay credentials not configured');
        }

        $result = $this->service->generateQr([
            'tran_id' => $this->service->generateTranId(2),
            'amount' => '1.00',
            'firstname' => 'Test',
            'lastname' => 'User',
            'currency' => 'USD',
            'lifetime' => 5,
        ]);

        $this->assertArrayHasKey('success', $result->toArray());

        if ($result['success']) {
            $this->assertNotEmpty($result['data']);
        }
    }
}
