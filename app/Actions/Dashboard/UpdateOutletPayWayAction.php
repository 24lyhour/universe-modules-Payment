<?php

namespace Modules\Payment\Actions\Dashboard;

use Modules\Outlet\Models\Outlet;

class UpdateOutletPayWayAction
{
    /**
     * Update outlet PayWay credentials.
     */
    public function execute(Outlet $outlet, array $data): Outlet
    {
        $updateData = [
            'payway_merchant_id' => $data['payway_merchant_id'],
            'payway_enabled' => $data['payway_enabled'] ?? true,
        ];

        // Only update api_key if provided
        if (!empty($data['payway_api_key'])) {
            $updateData['payway_api_key'] = $data['payway_api_key'];
        }

        $outlet->update($updateData);

        return $outlet->fresh();
    }
}
