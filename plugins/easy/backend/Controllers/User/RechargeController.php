<?php

namespace Plugins\Easy\Controllers\User;

use App\Bootstrap\ApiExceptions;
use App\Http\Controllers\User\BaseController;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Plugins\Easy\Models\Agiso;
use Throwable;

class RechargeController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 确认平台充值
     *
     * @throws Throwable
     */
    public function handle(Request $request): void
    {
        $tid = $request->input('tid');

        if (empty($tid)) {
            $this->error('订单号不能为空');
        }

        // 查询平台订单
        $agisoOrder = Agiso::where('tid', $tid)->first();

        if (! $agisoOrder) {
            $this->error('未找到该订单信息');
        }

        // 使用事务 + 行级锁处理充值，防止并发重复充值
        DB::beginTransaction();
        try {
            // 行级锁重新查询，防止 TOCTOU 竞态条件
            $agisoOrder = Agiso::where('id', $agisoOrder->id)->lockForUpdate()->first();

            // 检查订单是否已经充值
            if ($agisoOrder->recharged === 1) {
                DB::rollback();
                $this->error('该订单已经充值过了');
            }

            // 检查订单是否过期
            if ($agisoOrder->created_at?->timestamp < time() - 3600 * 24 * 90) {
                DB::rollback();
                $this->error('订单支付已超过90天，请联系客服处理');
            }

            // 获取平台对应的支付方式
            $payMethod = Agiso::getPlatform($agisoOrder->platform);

            // 计算赠送金额
            $giftAmount = bcsub((string) $agisoOrder->price, (string) $agisoOrder->amount, 2);

            /** @var User $user */
            $user = $this->guard->user();

            // 如果用户级别是 standard 或 gold 变更为 platinum
            if ($user->level_code === 'standard' || $user->level_code === 'gold') {
                $user->level_code = 'platinum';
                $user->save();
            }

            // 如果用户级别是 platinum 赠送金额，否则不赠送
            if ($user->level_code === 'platinum') {
                $amount = $agisoOrder->price;
                $remark = "订单金额$agisoOrder->amount";
                $remark .= bccomp($giftAmount, '0.00', 2) === 0 ? '' : "(赠送金额$giftAmount)";
            } else {
                $amount = $agisoOrder->amount;
                $remark = "订单金额$agisoOrder->amount";
            }

            // 创建充值记录
            Fund::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => 'addfunds',
                'pay_method' => $payMethod,
                'pay_sn' => $agisoOrder->tid,
                'status' => 1, // 直接设为成功状态
                'remark' => $remark,
                'ip' => request()->ip(),
            ]);

            // 标记平台订单为已充值
            $agisoOrder->recharged = 1;
            $agisoOrder->user_id = $user->id;
            $agisoOrder->save();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollback();
            app(ApiExceptions::class)->logException($e);
            $this->error('充值失败，请联系客服');
        }

        $this->success();
    }
}
