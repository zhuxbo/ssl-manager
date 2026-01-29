<?php

namespace App\Http\Traits;

use App\Http\Requests\Order\GetIdsRequest;
use App\Models\DomainValidationRecord;
use App\Models\Order;
use App\Models\User;
use App\Services\Notification\DTOs\NotificationIntent;
use App\Services\Notification\NotificationCenter;
use App\Services\Order\Action;
use Throwable;

/**
 * @property Action $action
 */
trait OrderController
{
    /**
     * 支付订单
     *
     * @throws Throwable
     */
    public function pay(int $id): void
    {
        $commit = request()->boolean('commit', true);
        $issueVerify = request()->boolean('issue_verify', true);
        $this->action->pay($id, $commit, $issueVerify);
    }

    /**
     * 提交订单
     *
     * @throws Throwable
     */
    public function commit(int $id): void
    {
        $this->action->commit($id);
    }

    /**
     * 重新验证
     */
    public function revalidate(int $id): void
    {
        // 重置域名验证记录，重新开始验证计时
        DomainValidationRecord::where('order_id', $id)->delete();
        $this->action->revalidate($id);
    }

    /**
     * 更新 DCV
     */
    public function updateDCV(int $id): void
    {
        // 重置域名验证记录，重新开始验证计时
        DomainValidationRecord::where('order_id', $id)->delete();
        $method = request()->string('method', '')->trim();
        $this->action->updateDCV($id, $method);
    }

    /**
     * 同步订单
     */
    public function sync(int $id): void
    {
        $this->action->sync($id);
    }

    /**
     * 提交取消订单
     *
     * @throws Throwable
     */
    public function commitCancel(int $id): void
    {
        $this->action->commitCancel($id);
    }

    /**
     * 撤销取消订单
     *
     * @throws Throwable
     */
    public function revokeCancel(int $id): void
    {
        $this->action->revokeCancel($id);
    }

    /**
     * 备注订单
     */
    public function remark(int $id): void
    {
        $remark = request()->string('remark')->trim()->limit(255);
        $this->action->remark($id, $remark);
    }

    /**
     * 下载证书
     */
    public function download(): void
    {
        $ids = request()->input('ids');
        $type = request()->input('type', 'all');
        $this->action->download($ids, $type);
    }

    /**
     * 下载验证文件
     */
    public function downloadValidateFile(int $id): void
    {
        $this->action->downloadValidateFile($id);
    }

    /**
     * 发送激活邮件
     */
    public function sendActive(int $id): void
    {
        $email = request()->string('email', '')->trim();
        $order = Order::with('user:id,email')->find($id);
        if (! $order || ! $order->user) {
            $this->error('订单或用户不存在');
        }

        $targetEmail = $email ?: $order->user->email;
        if (! $targetEmail) {
            $this->error('邮箱为空');
        }

        app(NotificationCenter::class)->dispatch(new NotificationIntent(
            'cert_issued',
            'user',
            $order->user->id,
            [
                'order_id' => $order->id,
                'email' => $targetEmail,
            ],
            ['mail']
        ));
        $this->success();
    }

    /**
     * 发送过期邮件
     */
    public function sendExpire(string $userId): void
    {
        $email = request()->string('email', '')->trim();
        $user = User::find((int) $userId);
        if (! $user) {
            $this->error('用户不存在');
        }

        $targetEmail = $email ?: $user->email;
        if (! $targetEmail) {
            $this->error('邮箱为空');
        }

        app(NotificationCenter::class)->dispatch(new NotificationIntent(
            'cert_expire',
            'user',
            $user->id,
            [
                'email' => $targetEmail,
            ],
            ['mail']
        ));
        $this->success();
    }

    /**
     * 批量支付
     *
     * @throws Throwable
     */
    public function batchPay(GetIdsRequest $request): void
    {
        $validated = $request->validated();
        $commit = request()->boolean('commit', true);
        $issueVerify = request()->boolean('issue_verify', true);
        $this->action->pay($validated['ids'], $commit, $issueVerify);
    }

    /**
     * 批量提交
     */
    public function batchCommit(GetIdsRequest $request): void
    {
        $validated = $request->validated();
        $this->action->batchCommit($validated['ids']);
    }

    /**
     * 批量重新验证
     */
    public function batchRevalidate(GetIdsRequest $request): void
    {
        $validated = $request->validated();

        // 重置域名验证记录，重新开始验证计时
        DomainValidationRecord::whereIn('order_id', $validated['ids'])->delete();

        $this->action->batchRevalidate($validated['ids']);
    }

    /**
     * 批量同步
     */
    public function batchSync(GetIdsRequest $request): void
    {
        $validated = $request->validated();
        $this->action->batchSync($validated['ids']);
    }

    /**
     * 批量提交取消
     *
     * @throws Throwable
     */
    public function batchCommitCancel(GetIdsRequest $request): void
    {
        $validated = $request->validated();
        $this->action->batchCommitCancel($validated['ids']);
    }

    /**
     * 批量撤销取消
     *
     * @throws Throwable
     */
    public function batchRevokeCancel(GetIdsRequest $request): void
    {
        $validated = $request->validated();
        $this->action->batchRevokeCancel($validated['ids']);
    }

    /**
     * 更新订单自动续费/重签设置
     */
    public function updateAutoSettings(int $id): void
    {
        $validated = request()->validate([
            'auto_renew' => 'boolean|nullable',
            'auto_reissue' => 'boolean|nullable',
        ]);

        $order = Order::where('id', $id);

        // 用户端只能更新自己的订单
        if ($this->action->getUserId()) {
            $order->where('user_id', $this->action->getUserId());
        }

        $order = $order->first();
        if (! $order) {
            $this->error('订单不存在');
        }

        if (array_key_exists('auto_renew', $validated)) {
            $order->auto_renew = $validated['auto_renew'];
        }
        if (array_key_exists('auto_reissue', $validated)) {
            $order->auto_reissue = $validated['auto_reissue'];
        }

        $order->save();

        $this->success([
            'auto_renew' => $order->auto_renew,
            'auto_reissue' => $order->auto_reissue,
        ]);
    }
}
