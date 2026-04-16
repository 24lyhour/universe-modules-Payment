<?php

namespace Modules\Payment\Actions\Dashboard;

use Modules\Outlet\Models\Outlet;

class RemoveOutletPayWayAction
{
    /**
     * Remove outlet PayWay credentials.
     */
    public function execute(Outlet $outlet): Outlet
    {
        $outlet->update([
            'payway_merchant_id' => null,
            'payway_api_key' => null,
            'payway_enabled' => false,
        ]);

        return $outlet->fresh();
    }
}
