<?php

namespace App\Console\Commands;

use App\Models\Cert;
use App\Models\Order;
use App\Models\User;
use App\Services\Notification\DTOs\NotificationIntent;
use App\Services\Notification\NotificationCenter;
use Illuminate\Console\Command;

class ExpireCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedule:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark as expired and send an expiration notification';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // 先更改所有到期证书的状态
        Cert::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        // 分区段查询，避免每天都发通知
        $user_ids = Order::with(['latestCert'])
            ->whereHas('latestCert', function ($query) {
                $query->where('status', 'active')
                    ->where(function ($query) {
                        $query->whereBetween('expires_at', [now()->addDays(29), now()->addDays(30)])
                            ->orWhereBetween('expires_at', [now()->addDays(15), now()->addDays(16)])
                            ->orWhereBetween('expires_at', [now()->addDays(7), now()->addDays(8)])
                            ->orWhereBetween('expires_at', [now()->addDays(3), now()->addDays(4)])
                            ->orWhereBetween('expires_at', [now(), now()->addDays()]);
                    })
                    ->orderBy('expires_at');
            })
            ->pluck('user_id')
            ->toArray();

        $this->info(get_system_setting('site', 'name', 'SSL证书管理系统'));
        $notificationCenter = app(NotificationCenter::class);

        foreach (array_unique($user_ids) as $user_id) {
            $user = User::find($user_id);
            if ($user && $user->email) {
                $notificationCenter->dispatch(new NotificationIntent(
                    'cert_expire',
                    'user',
                    $user->id,
                    [
                        'email' => $user->email,
                    ],
                    ['mail']
                ));
                $this->info("User $user->id email $user->email certificate expiration notification task created");
            }
        }
    }
}
