<?php

namespace App\Http\Controllers\Deploy;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class AcmeController extends Controller
{
    /**
     * 通过 Deploy Token 获取 EAB 凭据
     */
    public function getEab(Request $request): void
    {
        $userId = $request->attributes->get('authenticated_user_id');

        if (! $userId) {
            $this->error('Unauthorized');
        }

        $order = Order::where('user_id', $userId)
            ->whereHas('product', fn ($q) => $q->where('support_acme', 1))
            ->where('period_till', '>', now())
            ->whereNull('cancelled_at')
            ->whereNotNull('eab_kid')
            ->orderBy('period_till', 'desc')
            ->first();

        if (! $order) {
            $this->error('No available EAB credentials. Please request new EAB from your provider.');
        }

        $serverUrl = rtrim(get_system_setting('site', 'url', config('app.url')), '/').'/acme/directory';

        $this->success([
            'eab_kid' => $order->eab_kid,
            'eab_hmac' => $order->eab_hmac,
            'server_url' => $serverUrl,
        ]);
    }
}
