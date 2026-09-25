<?php

namespace Modules\Payment\Console;

use Illuminate\Console\Command;
use Modules\Payment\Services\PayWayService;

class TestPayWayCommand extends Command
{
    protected $signature = 'payway:test {--qr : Generate QR instead of purchase}';

    protected $description = 'Test PayWay API connection with sandbox credentials';

    public function handle(PayWayService $service): int
    {
        $this->info('Testing PayWay API...');
        $this->newLine();

        // Show current config
        $this->table(['Config', 'Value'], [
            ['Merchant ID', $service->getMerchantId() ?: '❌ NOT SET'],
            ['API Key', $service->getApiKey() ? substr($service->getApiKey(), 0, 10) . '...' : '❌ NOT SET'],
            ['Base URL', $service->getBaseUrl() ?: '❌ NOT SET'],
        ]);

        if (!$service->getMerchantId() || !$service->getApiKey()) {
            $this->error('❌ PayWay credentials not configured in .env');
            $this->newLine();
            $this->info('Add these to your .env file:');
            $this->line('PAYWAY_MERCHANT_ID=ec478284');
            $this->line('PAYWAY_API_KEY=cad43f0656c8a99761ded46a9c95074f33dc97bd');
            $this->line('PAYWAY_BASE_URL=https://checkout-sandbox.payway.com.kh');
            return 1;
        }

        $this->newLine();
        $tranId = $service->generateTranId(time());
        $this->info("Transaction ID: {$tranId}");

        if ($this->option('qr')) {
            return $this->testGenerateQr($service, $tranId);
        }

        return $this->testCreatePurchase($service, $tranId);
    }

    private function testCreatePurchase(PayWayService $service, string $tranId): int
    {
        $this->info('Testing createPurchase...');

        $result = $service->createPurchase([
            'tran_id' => $tranId,
            'amount' => '1.00',
            'firstname' => 'Test',
            'lastname' => 'User',
            'email' => 'test@example.com',
            'phone' => '012345678',
            'payment_option' => 'abapay_khqr_deeplink',
            'currency' => 'USD',
        ]);

        $this->newLine();

        if ($result['success']) {
            $this->info('✅ SUCCESS!');
            $this->newLine();

            $data = $result['data'];
            $this->table(['Field', 'Value'], [
                ['Status Code', $data['status']['code'] ?? 'N/A'],
                ['Status Message', $data['status']['message'] ?? 'N/A'],
                ['ABA Deeplink', isset($data['abapay_deeplink']) ? 'Yes (' . strlen($data['abapay_deeplink']) . ' chars)' : 'N/A'],
                ['QR String', isset($data['qr_string']) ? 'Yes (' . strlen($data['qr_string']) . ' chars)' : 'N/A'],
            ]);

            if (isset($data['abapay_deeplink'])) {
                $this->newLine();
                $this->info('ABA Deeplink (open in ABA app):');
                $this->line(substr($data['abapay_deeplink'], 0, 100) . '...');
            }

            return 0;
        }

        $this->error('❌ FAILED: ' . ($result['error'] ?? 'Unknown error'));

        if (isset($result['data'])) {
            $this->newLine();
            $this->line('Response: ' . json_encode($result['data'], JSON_PRETTY_PRINT));
        }

        return 1;
    }

    private function testGenerateQr(PayWayService $service, string $tranId): int
    {
        $this->info('Testing generateQr...');

        $result = $service->generateQr([
            'tran_id' => $tranId,
            'amount' => '1.00',
            'firstname' => 'Test',
            'lastname' => 'User',
            'currency' => 'USD',
            'lifetime' => 5,
            'qr_image_template' => 'template4_color',
        ]);

        $this->newLine();

        if ($result['success']) {
            $this->info('✅ SUCCESS!');
            $this->newLine();

            $data = $result['data'];
            $this->table(['Field', 'Value'], [
                ['Status Code', $data['status']['code'] ?? 'N/A'],
                ['QR String', isset($data['qr']) ? 'Yes (' . strlen($data['qr']) . ' chars)' : 'N/A'],
                ['QR Image', isset($data['qrImage']) ? 'Yes (base64)' : 'N/A'],
            ]);

            return 0;
        }

        $this->error('❌ FAILED: ' . ($result['error'] ?? 'Unknown error'));
        return 1;
    }
}
