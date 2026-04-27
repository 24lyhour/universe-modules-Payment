<?php

namespace Modules\Payment\Http\Middleware;

use App\Services\MenuService;
use Closure;
use Illuminate\Http\Request;

class DashboardMiddlewareHandle
{
    protected static bool $registered = false;

    public function handle(Request $request, Closure $next)
    {
        if ($request->is('dashboard', 'dashboard/*')) {
            $this->registerMenuItems();
        }

        return $next($request);
    }

    protected function registerMenuItems(): void
    {
        if (static::$registered) {
            return;
        }

        MenuService::addMenuItem(
            menu: 'primary',
            id: 'payment',
            title: __('Payment'),
            url: route('payment.transactions.index'),
            icon: 'CreditCard',
            order: 70,
            permissions: 'payments.view_any',
            route: 'payment.*'
        );

        MenuService::addSubmenuItem('primary', 'payment', __('Transactions'), route('payment.transactions.index'), 1, 'payments.view_any', 'payment.transactions.*', 'ArrowLeftRight');
        MenuService::addSubmenuItem('primary', 'payment', __('Settings'), route('payment.settings.index'), 2, 'payments.view_any', 'payment.settings.*', 'Settings');

        static::$registered = true;
    }
}
