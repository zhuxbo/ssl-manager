<?php

namespace App\Observers;

use App\Models\Cert;
use App\Models\Order;

class CertObserver
{
    public function saved(Cert $cert): void
    {
        if (! $cert->wasChanged('amount')) {
            return;
        }

        $this->recalculateOrderAmount($cert);
    }

    public function deleted(Cert $cert): void
    {
        $this->recalculateOrderAmount($cert);
    }

    private function recalculateOrderAmount(Cert $cert): void
    {
        if (! $cert->order_id) {
            return;
        }

        $order = Order::find($cert->order_id);
        if (! $order) {
            return;
        }

        $order->amount = Cert::where('order_id', $cert->order_id)->sum('amount');
        $order->save();
    }
}
