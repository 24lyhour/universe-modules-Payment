<?php

namespace Modules\Payment\Actions\Dashboard;

use Modules\Outlet\Models\Outlet;
use Modules\Payment\Services\PayWayService;

class TestOutletPayWayAction
{
    /**
     * Test outlet's PayWay connection.
     *
     * @return array{success: bool, message: string}
     */
    public function execute(Outlet $outlet): array
    {
        if (!$outlet->payway_merchant_id || !$outlet->payway_api_key) {
            return [
                'success' => false,
                'message' => 'PayWay credentials not configured.',
            ];
        }

        $service = app(PayWayService::class)->forOutlet($outlet);
        $result = $service->checkTransaction('test-' . time());

        if ($result['success']) {
            $code = $result['data']['status']['code'] ?? null;

            // Code 5 = "Transaction not found" = credentials are valid
            if ($code === 5 || $code === '5') {
                return [
                    'success' => true,
                    'message' => 'PayWay connection successful. Credentials are valid.',
                ];
            }

            // Code 1 = "Wrong hash" = bad api_key
            if ($code === 1 || $code === '1') {
                return [
                    'success' => false,
                    'message' => 'Invalid API key. Please check your credentials.',
                ];
            }
        }

        return [
            'success' => false,
            'message' => $result['error'] ?? 'Connection test failed.',
        ];
    }
}
