<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Exceptions\ApiResponseException;
use App\Http\Requests\Product\StoreRequest;
use App\Http\Requests\Product\UpdateRequest;
use App\Models\Callback;
use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\Notification\DTOs\NotificationIntent;
use App\Services\Notification\NotificationCenter;
use App\Services\Delegation\AutoDcvTxtService;
use App\Services\Order\Api\Api;
use App\Services\Order\Traits\ActionBatchTrait;
use App\Services\Order\Traits\ActionCallbackTrait;
use App\Services\Order\Traits\ActionFileTrait;
use App\Services\Order\Traits\ActionTrait;
use App\Services\Order\Utils\FindUtil;
use App\Services\Order\Utils\OrderUtil;
use App\Services\Order\Utils\VerifyUtil;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class Action
{
    use ActionBatchTrait;
    use ActionCallbackTrait;
    use ActionFileTrait;
    use ActionTrait;
    use ApiResponse;

    protected mixed $api;

    protected int $userId;

    public function __construct(int $userId = 0)
    {
        $this->userId = $userId;
        $this->api = new Api;
    }

    /**
     * 导入产品
     */
    public function importProduct(string $source = '', string $brand = '', string $api_id = '', string $type = 'new'): void
    {
        $products = $this->api->getProducts($source, $brand, $api_id);

        if ($products['code'] !== 1) {
            $this->error($products['msg'] ?? '获取产品失败');
        }

        if (empty($products['data'])) {
            $this->error('没有获取到产品');
        }

        foreach ($products['data'] as $item) {
            $item['source'] = $source;

            if (empty($item['code'])) {
                $this->error('产品 code 不能为空', $item);
            }

            $item['api_id'] = strval($item['code']);
            unset($item['code']);

            // 根据 api_id 查询产品
            $product = Product::where('source', $source)->where('api_id', $item['api_id'])->first();
            if ($product) {
                if ($type === 'update' || $type === 'all') {
                    // 使用 UpdateRequest 验证规则
                    $updateRequest = new UpdateRequest;
                    $updateRequest->setProductId($product->id);

                    // 将 $item 数据合并到请求中，以便 rules() 能正确判断产品类型
                    $updateRequest->merge($item);

                    $validator = Validator::make($item, $updateRequest->rules());
                    $validator->after(function ($validator) use ($updateRequest) {
                        $updateRequest->setValidator($validator);
                        $updateRequest->withValidator($validator);
                    });

                    if ($validator->fails()) {
                        $this->error('产品数据验证失败', $validator->errors()->toArray());
                    }

                    $product->update($item);
                }
            } else {
                if ($type === 'new' || $type === 'all') {
                    // 使用 StoreRequest 验证规则
                    $storeRequest = new StoreRequest;

                    $item['code'] = $item['api_id'];

                    // 将 $item 数据合并到请求中，以便 rules() 能正确判断产品类型
                    $storeRequest->merge($item);

                    $validator = Validator::make($item, $storeRequest->rules());
                    $validator->after(function ($validator) use ($storeRequest) {
                        $storeRequest->setValidator($validator);
                        $storeRequest->withValidator($validator);
                    });

                    if ($validator->fails()) {
                        $this->error('产品数据验证失败', $validator->errors()->toArray());
                    }

                    // 调用 prepareDataForCreate 方法处理默认值
                    $item = $storeRequest->prepareDataForCreate($item);
                    Product::create($item);
                }
            }
        }

        $this->success();
    }

    /**
     * 申请证书
     *
     * @throws Throwable
     */
    public function new(array $params): void
    {
        $later = $this->checkDuplicate('new', [$params, $this->userId], 10);
        $later && $this->error('参数重复，请在 '.$later.' 秒后再提交申请');

        $params = $this->initParams($params);

        $orderData = $this->getOrder($params);
        $latestCert = $this->getCert($params);
        $orderData['amount'] = $latestCert['amount'] = OrderUtil::getLatestCertAmount($orderData, $latestCert, $params['product']);

        DB::beginTransaction();
        try {
            $order = Order::create($orderData);
            $latestCert['order_id'] = $order->id;

            if ($latestCert['action'] == 'renew') {
                Cert::where(['status' => 'active', 'order_id' => $params['order_id']])->update(['status' => 'renewed']);
                $latestCert['last_cert_id'] = $order->latest_cert_id;
            }

            $cert = Cert::create($latestCert);
            $order->update(['latest_cert_id' => $cert->id]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollback();
            throw $e;
        }

        $this->success(['order_id' => $order->id]);
    }

    /**
     * 批量创建证书订单
     *
     * @throws Throwable
     */
    public function batchNew(array $params): void
    {
        $later = $this->checkDuplicate('batchNew', [$params, $this->userId], 10);
        $later && $this->error('参数重复，请在 '.$later.' 秒后再提交批量申请');

        $domains = explode(',', $params['domains'] ?? '');

        $orderIds = [];
        DB::beginTransaction();
        try {
            foreach ($domains as $item) {
                $params['domains'] = $item;

                $params = $this->initParams($params);

                $orderData = $this->getOrder($params);
                $latestCert = $this->getCert($params);

                $orderData['amount'] = $latestCert['amount'] = OrderUtil::getLatestCertAmount($orderData, $latestCert, $params['product']);

                $order = Order::create($orderData);
                $latestCert['order_id'] = $order->id;

                $cert = Cert::create($latestCert);
                $order->update(['latest_cert_id' => $cert->id]);

                $orderIds[] = $order->id;
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollback();
            throw $e;
        }

        $this->success(['order_ids' => $orderIds]);
    }

    /**
     * 续费
     *
     * @throws Throwable
     */
    public function renew(array $params): void
    {
        $later = $this->checkDuplicate('renew', [$params, $this->userId], 10);
        $later && $this->error('参数重复，请在 '.$later.' 秒后再提交续费');

        $this->new($params);
    }

    /**
     * 重签
     *
     * @throws Throwable
     */
    public function reissue(array $params): void
    {
        $later = $this->checkDuplicate('reissue', [$params, $this->userId], 10);
        $later && $this->error('参数重复，请在 '.$later.' 秒后再提交重签');

        $params = $this->initParams($params);

        $order = Order::find($params['order_id']);

        $order->organization = $params['organization'] ?? $order->organization;
        $latestCert = $this->getCert($params);

        $amount = OrderUtil::getLatestCertAmount($order->toArray(), $latestCert, $params['product']);

        // 产品禁用后 重签不能增加域名个数
        if (bccomp($amount, '0', 2) === 1) {
            $product = FindUtil::Product((int) $order->product_id);
            if ($product->status == 0) {
                $this->error('此订单重签不能增加域名个数');
            }
        }

        DB::beginTransaction();
        try {
            Cert::where('id', $order->latest_cert_id)->update(['status' => 'reissued']);

            $latestCert['order_id'] = $order->id;
            $latestCert['last_cert_id'] = $order->latest_cert_id;
            $latestCert['amount'] = $amount;
            $latestCert['status'] = 'unpaid';

            $cert = Cert::create($latestCert);
            $order->latest_cert_id = $cert->id;
            $order->amount = bcadd((string) $order->amount, $amount, 2);
            $order->save();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollback();
            throw $e;
        }

        $this->success(['order_id' => $order->id]);
    }

    /**
     * 支付订单
     *
     * @throws Throwable
     */
    public function pay(int|string|array $orderIds, bool $commit = true, bool $issueVerify = false): void
    {
        $orderIds = is_array($orderIds) ? $orderIds : explode(',', (string) $orderIds);
        $orderIds = array_map('intval', $orderIds);

        count($orderIds) > 20 && $this->error('订单数量不能超过20');

        $issueVerify && VerifyUtil::issueVerify($orderIds);

        if (count($orderIds) === 1) {
            $charge = $this->charge($orderIds[0], false);
            $charge['status'] === 'failed' && $this->error($charge['msg'], $charge['errors'] ?? null);

            // 只有一个订单的时候，支付成功后立即提交
            $commit && $this->commit($orderIds[0]);
        } else {
            $result = [];
            foreach ($orderIds as $key => $orderId) {
                $charge = $this->charge($orderId, $commit);
                $charge['status'] === 'failed' && $result[$key] = $charge;
            }

            $result && $this->error('批量支付失败', $result);
        }

        $this->success();
    }

    /**
     * 提交
     *
     * @throws Throwable
     */
    public function commit(int $orderId): void
    {
        DB::beginTransaction();
        try {
            // 事务查询不锁定产品
            $order = Order::with(['latestCert'])
                ->whereHas('user')
                ->whereHas('latestCert')
                ->lock()
                ->find($orderId);

            if (! $order) {
                $this->error('订单或相关数据不存在');
            }

            $order->latestCert->status != 'pending' && $this->error('订单状态不是待提交');

            // 提交订单
            $product = FindUtil::Product($order->product_id);

            $action = $order->latestCert->action;
            $data = $order->latestCert->toArray();
            $data['product_api_id'] = $product->api_id;
            $data['source'] = $product->source;
            $data['product_type'] = $product->product_type ?? 'ssl';
            $data['period'] = $order->period;
            $data['plus'] = $order->plus;
            $data['contact'] = $order->contact;
            $data['csr'] = $order->latestCert->csr;

            if ($data['product_type'] === 'smime') {
                $data['email'] = $order->latestCert->email;
            }

            if ($product->validation_type != 'dv') {
                $data['organization'] = $order->organization;
            }

            if ($order->latestCert->last_cert_id) {
                $lastCert = Cert::where('id', $order->latestCert->last_cert_id)->first();
                $data['last_api_id'] = $lastCert->api_id;

                // 上个证书的域名列表 用于重签时去除已有域名 部分 CA 重签仅接收新域名
                $data['last_cert'] = $lastCert->cert;
                $data['last_alternative_names'] = $lastCert->alternative_names;
            }

            $result = $this->api->$action($data);
            $apiId = $result['data']['api_id'] ?? '';

            // 返回 code 不等于 1 或者 api_id 为空
            if ($result['code'] !== 1 || ! $apiId) {
                $this->error($result['msg'] ?? '提交失败', $result['errors'] ?? null);
            }

            $order->latestCert->api_id = $apiId;
            $order->latestCert->cert_apply_status = $result['data']['cert_apply_status'] ?? 0;
            $order->latestCert->dcv = $result['data']['dcv'] ?? $order->latestCert->dcv;
            $order->latestCert->validation = $result['data']['validation'] ?? $order->latestCert->validation;
            $order->latestCert->status = 'processing';
            $order->latestCert->save();
            DB::commit();
        } catch (Throwable $e) {
            DB::rollback();
            throw $e;
        }

        $this->success([
            'order_id' => $orderId,
            'cert_apply_status' => $result['data']['cert_apply_status'] ?? 0,
            'dcv' => $result['data']['dcv'] ?? $data['dcv'] ?? null,
            'validation' => $result['data']['validation'] ?? $data['validation'] ?? null,
        ]);
    }

    /**
     * 同步证书信息
     */
    public function sync(int $orderId, bool $force = false): void
    {
        // 10秒内仅请求一次 API 避免重复请求
        if ($this->checkDuplicate('sync', [$orderId, $this->userId], 10)) {
            if ($force) {
                return;
            } else {
                $this->success();
            }
        }

        $order = Order::with(['user', 'product', 'latestCert'])
            ->whereHas('user')
            ->whereHas('product')
            ->whereHas('latestCert')
            ->find($orderId);

        if (! $order) {
            $this->error('订单或相关数据不存在');
        }

        $user = $order->user;
        $cert = $order->latestCert;

        $cert->status == 'unpaid' && $this->error('订单未支付');
        $cert->status == 'pending' && $this->error('订单未提交');

        if ($force) {
            $result = $this->api->get($orderId);

            // 这些订单状态可以强制更新 但状态不能改变, cancelling 可以改变
            if (in_array($cert->status, ['cancelled', 'revoked', 'renewed', 'reissued', 'failed'])) {
                unset($result['data']['status']);
            }
        } else {
            if (! in_array($cert->status, ['processing', 'approving', 'active'])) {
                $this->error('只有订单状态为待验证、待批准、已签发才能同步');
            }

            $result = $this->api->get($orderId);
        }

        $data = $result['data'] ?? [];

        // 合并 validation
        $data['validation'] = isset($data['validation'])
            ? $this->mergeValidation($data['validation'], $cert->validation ?? [])
            : $cert->validation;

        is_array($data['contact'] ?? null) && $order->contact = array_merge($order->contact ?? [], $data['contact']);
        is_array($data['organization'] ?? null) && $order->organization = array_merge($order->organization ?? [], $data['organization']);
        unset($data['contact'], $data['organization']);

        if ($data['alternative_names'] ?? false) {
            // 重新获取证书域名个数
            $sanCount = OrderUtil::getSansFromDomains($data['alternative_names'], $order->product->gift_root_domain);

            $data['standard_count'] = $sanCount['standard_count'] ?? 0;
            $data['wildcard_count'] = $sanCount['wildcard_count'] ?? 0;

            // 如果是导入 初始化已购域名个数
            ! $order->purchased_standard_count && $order->purchased_standard_count = $data['standard_count'];
            ! $order->purchased_wildcard_count && $order->purchased_wildcard_count = $data['wildcard_count'];
        }

        if (! empty($data['cert'])) {
            // 解析证书
            $data = array_merge($data, $this->parseCert($data['cert']));
        }

        // 如果是新订单，设置订单有效期
        if (! $order->period_from && ($data['issued_at'] ?? null) && ($data['expires_at'] ?? null)) {
            // 即使传递的是时间戳 赋值给模型属性后会转换为时间格式
            $order->period_from = $data['issued_at'];
            $periodTill = $this->addMonths((int) $data['issued_at'], (int) $order->period);
            $order->period_till = max($data['expires_at'], $periodTill);
        }

        // 状态是否变化
        $hasStatusChanged = isset($data['status']) && $data['status'] !== $cert->status;

        // 证书签发后发送通知邮件
        if ($hasStatusChanged && $data['status'] === 'active' && $user->email) {
            app(NotificationCenter::class)->dispatch(new NotificationIntent(
                'cert_issued',
                'user',
                $user->id,
                [
                    'order_id' => $order->id,
                    'email' => $user->email,
                ],
                ['mail']
            ));
        }

        // 签发 取消 吊销 发起回调
        if ($hasStatusChanged && in_array($data['status'] ?? '', ['active', 'cancelled', 'revoked'])) {
            $callback = Callback::where('user_id', $order->user_id)->where('status', 1)->first();
            $callback && $this->createTask($orderId, 'callback');
            // 删除相关任务
            $this->deleteTask($orderId, 'commit,sync,revalidate');
        }

        $order->save();
        $cert->update($data);

        // 强制更新不返回提示
        $force || $this->success();
    }

    /**
     * 过户
     */
    public function transfer(array $params): void
    {
        $params = OrderUtil::convertNumericValues($params);

        FindUtil::User((int) $params['user_id'], true);

        $order = Order::find($params['order_id']);
        $order->user_id = $params['user_id'];
        $order->save();

        $this->success();
    }

    /**
     * 导入证书 必须先导入新证书，再导入替换或重签的证书
     *
     * @throws Throwable
     */
    public function input(array $params): void
    {
        $params = OrderUtil::convertNumericValues($params);

        FindUtil::User((int) $params['user_id'], true);

        $product = FindUtil::Product((int) $params['product_id']);
        in_array($params['period'], $product->periods) || $this->error('有效期错误');

        $certData['api_id'] = $params['api_id'] ?? '';
        $certData['action'] = $params['action'] ?? 'new';
        $certData['channel'] = $params['channel'] ?? 'admin';
        $certData['common_name'] = $params['common_name'] ?? '';
        $certData['csr'] = $params['csr'] ?? null;
        $certData['private_key'] = $params['private_key'] ?? null;
        $certData['status'] = 'approving';

        if (in_array($certData['action'], ['new', 'renew'])) {
            $orderData['user_id'] = (int) $params['user_id'];
            $orderData['product_id'] = (int) $params['product_id'];
            $orderData['period'] = (int) $params['period'];
            $orderData['brand'] = $product->brand;
        }

        if ($certData['action'] == 'reissue') {
            $certData['order_id'] = $params['order_id'] ?? null;
        }

        DB::beginTransaction();
        try {
            $cert = Cert::where('api_id', $params['api_id'])->first();
            if ($cert) {
                $cert->fill($certData);
                $cert->save();
                $order = FindUtil::Order($cert->order_id);
                if (! empty($orderData)) {
                    $order->fill($orderData);
                    $order->save();
                }
            } else {
                $cert = Cert::create($certData);

                if (isset($orderData)) {
                    $order = Order::create($orderData);
                } else {
                    $order = FindUtil::Order($cert->order_id);
                }

                $cert->last_cert_id = $order->latest_cert_id;
                $cert->order_id = $order->id;
                $cert->save();

                $order->latest_cert_id = $cert->id;
                $order->save();
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollback();
            throw $e;
        }

        isset($order->id) && $this->sync($order->id, true);
        $this->success();
    }

    /**
     * 重新验证
     */
    public function revalidate(int $orderId): void
    {
        $later = $this->checkDuplicate('revalidate', [$orderId, $this->userId]);
        $later && $this->error('请在 '.$later.' 秒后再提交验证');

        $order = FindUtil::Order($orderId);
        $cert = $order->latestCert;
        $cert->status != 'processing' && $this->error('订单状态只有是待验证才能重新验证');

        // 创建 delegation 任务处理 TXT 记录写入（SMIME/CodeSign/DocSign 没有 DCV）
        if (isset($cert->dcv['method']) && $cert->dcv['method'] === 'txt') {
            // 检测 validation 是否为空
            $isEmpty = empty($cert->validation) || ! is_array($cert->validation);

            if (! $isEmpty) {
                // 检测是否需要处理委托
                $autoDcvService = new AutoDcvTxtService;
                $shouldProcessDelegation = $autoDcvService->shouldProcessDelegation($order);

                // 创建委托任务
                $shouldProcessDelegation && $this->createTask($orderId, 'delegation');
            }
        }

        $this->createTask($orderId, 'sync', 30);

        $this->api->revalidate($orderId);

        $this->success();
    }

    /**
     * 处理委托解析（自动写入TXT记录）
     */
    public function delegation(int $orderId): void
    {
        $order = FindUtil::Order($orderId);

        // 使用 AutoDcvTxtService 处理委托解析
        $autoDcvService = new AutoDcvTxtService;
        $isDelegated = $autoDcvService->handleOrder($order);

        if (! $isDelegated) {
            $this->error("订单 #$orderId 委托解析处理失败或未命中配置");
        }

        $this->success();
    }

    /**
     * 修改验证方法
     */
    public function updateDCV(int $orderId, string $method): void
    {
        $later = $this->checkDuplicate('updateDCV', [$orderId, $this->userId]);
        $later && $this->error('请在 '.$later.' 秒后再提交修改');

        $order = FindUtil::Order($orderId);
        $cert = $order->latestCert;

        // 验证域名和验证方法的兼容性
        $this->validateDomainValidationCompatibility($cert->alternative_names, $method);

        if (in_array($cert->status, ['unpaid', 'pending'])) {
            $cert->dcv = $this->generateDcv($order->product->ca, $method, $cert->csr, $cert->unique_value ?? '');
            $cert->validation = $this->generateValidation($cert->dcv, $cert->alternative_names);
        } elseif ($cert->status === 'processing') {
            $result = $this->api->updateDCV($orderId, $method);
            $cert->dcv = $result['data']['dcv'] ?? $cert->dcv;
            $cert->validation = $result['data']['validation'] ?? $cert->validation;
        } else {
            $this->error('此订单状态不支持修改验证方法，请刷新页面查看');
        }

        $cert->save();

        $this->success([
            'dcv' => $result['data']['dcv'] ?? $cert->dcv,
            'validation' => $result['data']['validation'] ?? $cert->validation,
        ]);
    }

    /**
     * 提交取消
     *
     * @throws Throwable
     */
    public function commitCancel(int $orderId): void
    {
        $order = FindUtil::Order($orderId);
        $product = FindUtil::Product($order->product_id);

        // 待支付 待提交 订单不限制取消时间
        $status = $order->latestCert->status;
        $status === 'unpaid' && $this->delete($orderId);
        $status === 'pending' && $this->cancelPending($orderId);

        $status === 'cancelled' && $this->error('订单已取消');
        $status === 'expired' && $this->error('订单已过期');
        $status === 'renewed' && $this->error('订单已续期');
        $status === 'reissued' && $this->error('订单已重签');
        $status === 'cancelling' && $this->error('订单取消中');
        $status === 'revoked' && $this->error('订单已吊销');
        $status === 'failed' && $this->error('订单已失败');

        if (in_array($status, ['processing', 'approving', 'active'])) {
            $refundPeriod = $product->refund_period ?? 0;
            $order->created_at->timestamp < time() - 86400 * $refundPeriod
            && $this->error("订单已超过 $refundPeriod 天不能取消");

            // 2分钟后取消
            $order->latestCert->update(['status' => 'cancelling']);
            $this->deleteTask($orderId, 'sync,revalidate');
            $this->createTask($orderId, 'cancel');
        }

        $this->success();
    }

    /**
     * 撤回取消
     */
    public function revokeCancel(int $orderId): void
    {
        $order = FindUtil::Order($orderId);

        $this->deleteTask($orderId, 'cancel');
        $order->latestCert->update(['status' => 'approving']);
        $this->createTask($orderId, 'sync');

        $this->success();
    }

    /**
     * 取消证书
     *
     * @throws Throwable
     */
    public function cancel(int $orderId): void
    {
        DB::beginTransaction();
        try {
            // 事务查询不锁定产品
            $order = Order::with(['latestCert'])
                ->whereHas('user')
                ->whereHas('latestCert')
                ->lock()
                ->find($orderId);

            if (! $order) {
                $this->error('订单或相关数据不存在');
            }

            $product = FindUtil::Product($order->product_id);

            $order->created_at->timestamp < time() - 86400 * $product->refund_period
            && $this->error('订单已超过'.$product->refund_period.'天');

            $order->latestCert->status === 'cancelled' && $this->error('订单已取消');
            $order->latestCert->status != 'cancelling' && $this->error('订单状态不是取消中');

            try {
                $this->api->cancel($orderId);
            } catch (ApiResponseException $e) {
                $errors = $e->getApiResponse()['errors'] ?? null;
                $msg = $e->getApiResponse()['msg'] ?: 'CA取消失败';
                $this->error($msg, $errors);
            }

            // 获取交易信息
            $transaction = OrderUtil::getCancelTransaction($order->toArray());

            // 创建交易记录并退款
            Transaction::create($transaction);

            // 更新订单状态
            $order->latestCert->update(['status' => 'cancelled']);

            // 保存取消时间
            $order->update(['cancelled_at' => now()]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollback();
            throw $e;
        }

        $this->success();
    }

    /**
     * 备注
     */
    public function remark(int $orderId, string $remark): void
    {
        $order = FindUtil::Order($orderId);

        $field = $this->userId ? 'remark' : 'admin_remark';
        $order->update([$field => $remark]);

        $this->success();
    }
}
