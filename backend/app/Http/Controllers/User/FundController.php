<?php

namespace App\Http\Controllers\User;

use App\Bootstrap\ApiExceptions;
use App\Http\Requests\Fund\IndexRequest;
use App\Models\Agiso;
use App\Models\Fund;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;
use Yansongda\Pay\Pay;

/**
 * 资金管理
 */
class FundController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取资金列表
     */
    public function index(IndexRequest $request): void
    {
        $validated = $request->validated();
        $currentPage = (int) ($validated['currentPage'] ?? 1);
        $pageSize = (int) ($validated['pageSize'] ?? 10);

        $query = Fund::query();

        // 添加搜索条件
        if (! empty($validated['id'])) {
            $query->where('id', $validated['id']);
        }
        if (! empty($validated['amount'])) {
            if (isset($validated['amount'][0]) && isset($validated['amount'][1])) {
                $query->whereBetween('amount', $validated['amount']);
            } elseif (isset($validated['amount'][0])) {
                $query->where('amount', '>=', $validated['amount'][0]);
            } elseif (isset($validated['amount'][1])) {
                $query->where('amount', '<=', $validated['amount'][1]);
            }
        }
        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }
        if (! empty($validated['pay_method'])) {
            $query->where('pay_method', $validated['pay_method']);
        }
        if (! empty($validated['pay_sn'])) {
            $query->where('pay_sn', $validated['pay_sn']);
        }
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['created_at'])) {
            $query->whereBetween('created_at', $validated['created_at']);
        }

        $total = $query->count();
        $items = $query->select([
            'id', 'amount', 'type', 'pay_method', 'pay_sn', 'status', 'remark', 'created_at',
        ])
            ->where('status', '!=', 0)
            ->orderBy('id', 'desc')
            ->offset(($currentPage - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        $this->success([
            'items' => $items,
            'total' => $total,
            'pageSize' => $pageSize,
            'currentPage' => $currentPage,
        ]);
    }

    /**
     * 检查充值状态
     *
     * @throws Throwable
     */
    public function check(string $id): void
    {
        $fund = Fund::where([
            'id' => $id,
            'type' => 'addfunds',
            'status' => 0, // processing
        ])->first();

        if (! $fund) {
            $this->error('invalid fund id');
        }

        if ($fund->pay_method === 'alipay') {
            $config = Setting::getByGroupName('alipay');
            Pay::config($config);
            $order = Pay::alipay()->query(['out_trade_no' => $fund->id]);
            if ($order->trade_status === 'TRADE_SUCCESS' || $order->trade_status === 'TRADE_FINISHED') {
                $pay_sn = $order->trade_no;
            }
        }

        if ($fund->pay_method === 'wechat') {
            $config = Setting::getByGroupName('wechat');
            Pay::config($config);
            $order = Pay::wechat()->query(['out_trade_no' => $fund->id]);
            if ($order->trade_state === 'SUCCESS') {
                $pay_sn = $order->transaction_id;
            }
        }

        // 如果支付序列号存在则充值成功
        if (isset($pay_sn)) {
            $this->addfundsSuccessful($id, $pay_sn);
            $this->success();
        }

        $this->error('未查询到支付信息');
    }

    /**
     * 充值成功 使用事务 防止重复
     *
     * @throws Throwable
     */
    protected function addfundsSuccessful(string $id, int|string $pay_sn): void
    {
        DB::beginTransaction();
        try {
            $fund = Fund::where([
                'id' => $id,
                'type' => 'addfunds',
                'status' => 0, // processing
            ])->lockForUpdate()->first();

            if ($fund) {
                $fund->status = 1; // successful
                $fund->pay_sn = $pay_sn;
                $fund->save();
                DB::commit();
            }
        } catch (Throwable $e) {
            DB::rollback();
            app(ApiExceptions::class)->logException($e);
            $this->error('充值失败：'.$e->getMessage());
        }
    }

    /**
     * 确认平台充值
     *
     * @throws Throwable
     */
    public function platformRecharge(Request $request): void
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

        // 检查订单是否已经充值
        if ($agisoOrder->recharged === 1) {
            $this->error('该订单已经充值过了');
        }

        // 检查订单是否过期
        if ($agisoOrder->created_at?->timestamp < time() - 3600 * 24 * 90) {
            $this->error('订单支付已超过90天，请联系客服处理');
        }

        // 使用事务处理充值
        DB::beginTransaction();
        try {
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
