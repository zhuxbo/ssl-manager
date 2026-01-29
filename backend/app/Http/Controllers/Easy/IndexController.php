<?php

declare(strict_types=1);

namespace App\Http\Controllers\Easy;

use App\Bootstrap\ApiExceptions;
use App\Exceptions\ApiResponseException;
use App\Http\Controllers\Controller;
use App\Models\Agiso;
use App\Models\Fund;
use App\Models\Product;
use App\Models\User;
use App\Services\Notification\DTOs\NotificationIntent;
use App\Services\Notification\NotificationCenter;
use App\Services\Order\Action;
use App\Utils\Random;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class IndexController extends Controller
{
    /**
     * 检查订单状态
     */
    public function check(Request $request): void
    {
        $params = $request->all();

        if (empty($params['email'] ?? '')) {
            $this->init($params);
        }

        $this->getStep($params);

        $this->error('未知错误');
    }

    /**
     * 获取订单信息
     */
    protected function getOrder(array $params): Agiso
    {
        $order = Agiso::with(['user', 'order', 'latestCert', 'product'])
            ->where('tid', $params['tid'] ?? '')
            ->whereHas('user', function ($query) use ($params) {
                $query->where('email', $params['email'] ?? '');
            })
            ->first();

        if (! $order) {
            $this->error('订单与邮箱不匹配');
        }

        if (! $order->order) {
            $this->error('订单错误');
        }

        if (! $order->latestCert) {
            $this->error('订单错误');
        }

        if (! $order->product) {
            $this->error('订单错误');
        }

        return $order;
    }

    /**
     * 初始化订单检查
     */
    protected function init(array $params): void
    {
        $order = Agiso::where('tid', $params['tid'])->first();

        if (! $order) {
            $this->error('订单不存在');
        }

        if ($order->created_at->timestamp < time() - 3600 * 24 * 90) {
            $this->error('订单支付已超过90天，简易申请关闭');
        }

        if ($order->count > 1) {
            $this->error('一次购买多件不支持简易申请');
        }

        $product = $this->getProduct($order);
        if (empty($product)) {
            $this->error('此产品不支持简易申请');
        }

        if ($order->recharged === 0) {
            $this->success([
                'step' => 0,
                'product' => [
                    'name' => $product['name'],
                    'is_wildcard' => $product['is_wildcard'],
                    'validation_methods' => $product['validation_methods'],
                ],
                'is_applied' => 0,
            ]);
        }

        if ($order->recharged === 1 && ! $order->order_id) {
            $this->error('订单已使用');
        }

        if ($order->recharged === 1 && empty($params['email'])) {
            $this->success([
                'step' => 0,
                'product' => ['name' => $product['name']],
                'is_applied' => 1,
            ]);
        }
    }

    /**
     * 获取当前步骤
     */
    public function getStep(array $params): void
    {
        $order = $this->getOrder($params);

        if (in_array($order->latestCert->status, ['processing', 'pending', 'unpaid'])) {
            $product = $this->getProduct($order);
            $this->success([
                'step' => 1,
                'product' => [
                    'ca' => $product['ca'],
                    'is_wildcard' => $product['is_wildcard'],
                    'validation_methods' => $product['validation_methods'],
                ],
                'validation' => $order->latestCert->validation[0] ?? [],
                'status' => 'processing',
            ]);
        }

        if ($order->latestCert->status === 'approving') {
            $this->success([
                'step' => 2,
                'validation' => $order->latestCert->validation[0] ?? [],
                'status' => 'approving',
            ]);
        }

        if ($order->latestCert->status === 'active') {
            $this->success([
                'step' => 3,
                'validation' => $order->latestCert->validation[0] ?? [],
                'cert' => $order->latestCert->cert."\n".$order->latestCert->intermediate_cert,
                'key' => $order->latestCert->private_key,
                'status' => 'active',
            ]);
        }

        $this->error('订单状态错误');
    }

    /**
     * 重新验证
     *
     * @throws Throwable
     */
    public function revalidate(Request $request): void
    {
        $params = $request->all();
        $order = $this->getOrder($params);
        $action = new Action($order->user->id);

        if ($order->latestCert->status === 'processing') {
            try {
                $action->revalidate($order->order_id);
            } catch (ApiResponseException $e) {
                $result = $e->getApiResponse();
                if ($result['code'] === 0) {
                    $action->createTask($order->order_id, 'sync', 5);
                    $this->error($result['msg'], $result['errors'] ?? null);
                }
            }
        }

        if ($order->latestCert->status === 'pending') {
            try {
                $action->commit($order->order_id);
            } catch (ApiResponseException $e) {
                $result = $e->getApiResponse();
                if ($result['code'] === 0) {
                    $this->error($result['msg'], $result['errors'] ?? null);
                }
            }
        }

        if ($order->latestCert->status === 'unpaid') {
            try {
                $action->pay($order->order_id);
            } catch (ApiResponseException $e) {
                $result = $e->getApiResponse();
                if ($result['code'] === 0) {
                    $this->error($result['msg'], $result['errors'] ?? null);
                }
            }
        }

        if ($order->latestCert->status === 'approving') {
            $action->createTask($order->order_id, 'sync', 5);
            $this->getStep($params);
        }

        if ($order->latestCert->status === 'active') {
            $this->getStep($params);
        }

        $this->success();
    }

    /**
     * 同步订单状态
     */
    public function sync(Request $request): void
    {
        $params = $request->all();
        $order = $this->getOrder($params);

        (new Action($order->user->id))->sync($order->order_id, true);

        $this->getStep($params);

        $this->success();
    }

    /**
     * 申请证书
     *
     * @throws Throwable
     */
    public function apply(Request $request): void
    {
        $params = $request->all();

        $order = Agiso::where('tid', $params['tid'] ?? '')->first();
        if (! $order) {
            $this->error('订单不存在');
        }

        if ($order->recharged === 1 && ! $order->order_id) {
            $this->error('订单已使用');
        }

        if ($order->created_at->timestamp < time() - 3600 * 24 * 90) {
            $this->error('订单支付已超过 90 天，简易申请关闭');
        }

        if ($order->count > 1) {
            $this->error('一次购买多件不支持简易申请');
        }

        $product = $this->getProduct($order);
        if (empty($product)) {
            $this->error('此产品不支持简易申请');
        }

        $this->validateApplyParams($params);

        if ($order->user_id) {
            $user = User::where('id', $order->user_id)->first();
            if (! $user) {
                $this->error('订单关联用户错误');
            }

            if ($user->email !== $params['email']) {
                $this->error('订单与邮箱不匹配');
            } else {
                // 如果订单与邮箱匹配直接返回成功 让前端重新检查订单状态
                $this->success();
            }
        }

        $from = '';
        if ($order->platform === 'TbAlds') {
            $from = 'taobao';
        } elseif ($order->platform === 'PddAlds') {
            $from = 'pinduoduo';
        }

        $user = $this->getUserByEmail($params['email'], $from);

        // 使用事务 充值并创建订单
        DB::beginTransaction();
        try {
            $isPlatinum = false;
            if ($user->level_code === 'platinum' && empty($user->custom_level_code)) {
                $isPlatinum = true;
            }

            $orderGiftAmount = $isPlatinum ? bcsub((string) $order->price, (string) $order->amount, 2) : '0.00';

            $remark = "订单金额$order->amount";
            $remark .= bccomp($orderGiftAmount, '0.00', 2) === 0 ? '' : "(赠送金额$orderGiftAmount)";

            // 订单充值
            Fund::create([
                'user_id' => $user->id,
                'amount' => $isPlatinum ? $order->price : $order->amount,
                'type' => 'addfunds',
                'pay_method' => Agiso::getPlatform($order->platform),
                'pay_sn' => $order->tid,
                'status' => 1,
                'remark' => $remark,
            ]);

            // 提交证书申请
            try {
                (new Action($user->id))->new([
                    'domains' => $params['domain'],
                    'product_id' => $product['id'],
                    'period' => $product['period'],
                    'validation_method' => $params['validation_method'],
                    'action' => 'new',
                    'channel' => 'web',
                    'csr_generate' => 1,
                    'plus' => 1,
                ]);
            } catch (ApiResponseException $e) {
                $result = $e->getApiResponse();
                if ($result['code'] === 1) {
                    $orderId = $result['data']['order_id'];
                } else {
                    $this->error($result['msg'] ?? '证书提交失败', $result['errors'] ?? null);
                }
            }

            // 支付并提交订单
            try {
                if (isset($orderId)) {
                    (new Action($user->id))->pay($orderId, true, true);
                }
            } catch (ApiResponseException $e) {
                $result = $e->getApiResponse();
                if ($result['code'] === 1) {
                    $orderId = $result['data']['order_id'];
                } else {
                    $this->error($result['msg'] ?? '证书支付失败', $result['errors'] ?? null);
                }
            }

            $order->update([
                'user_id' => $user->id,
                'order_id' => $orderId ?? null,
                'recharged' => 1,
            ]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollback();
            throw $e;
        }

        $this->success();
    }

    /**
     * 更新验证方法
     */
    public function updateValidationMethod(Request $request): void
    {
        $params = $request->all();
        $order = $this->getOrder($params);

        $action = new Action($order->user->id);

        try {
            $action->updateDCV($order->order_id, $params['validation_method'] ?? 'cname');
        } catch (ApiResponseException $e) {
            $result = $e->getApiResponse();
            if ($result['code'] === 0) {
                // 防止订单信息与 CA 未同步
                $action->createTask($order->order_id, 'sync', 10);
                $this->error($result['msg'], $result['errors'] ?? null);
            }
        }
    }

    /**
     * 验证文件
     */
    public function validateFile(Request $request): void
    {
        $params = $request->all();
        $order = $this->getOrder($params);
        (new Action($order->user->id))->downloadValidateFile($order->order_id);
    }

    /**
     * 下载证书
     */
    public function download(Request $request): void
    {
        $params = $request->all();
        $order = $this->getOrder($params);
        (new Action($order->user->id))->download($order->order_id, $params['type'] ?? 'all');
    }

    /**
     * 获取产品配置
     */
    protected function getProduct(Agiso $order): array
    {
        if ($order->count > 1) {
            return [];
        }

        // 检查 product_code 是否存在
        if (empty($order->product_code)) {
            return [];
        }

        // 根据 product_code 查询产品
        $product = Product::where('code', $order->product_code)->first();
        if (! $product || $product->validation_type !== 'dv') {
            return [];
        }

        // Todo: Sectigo 暂时屏蔽 txt 解析，待 TXT 记录获取方法完善后放开
        if (strtolower($product->ca) === 'sectigo') {
            $product->validation_methods = array_filter($product->validation_methods, function ($method) {
                return $method !== 'txt';
            });
        }

        // 根据产品信息生成easy需要的数据结构
        return [
            'name' => $product->name,
            'ca' => $product->brand,
            'id' => $product->id,
            'period' => $order->period ?? 1,
            'is_wildcard' => $this->isWildcardSupported($product->common_name_types),
            'validation_methods' => $this->parseValidationMethods($product->validation_methods),
        ];
    }

    /**
     * 判断是否支持通配符
     * common_name_types 包含 wildcard 则支持通配
     */
    protected function isWildcardSupported(?array $commonNameTypes): int
    {
        if (empty($commonNameTypes) || ! is_array($commonNameTypes)) {
            return 0;
        }

        // 如果包含wildcard，返回1
        if (in_array('wildcard', $commonNameTypes)) {
            return 1;
        }

        // 否则返回0
        return 0;
    }

    /**
     * 解析验证方法
     */
    protected function parseValidationMethods($validationMethods): array
    {
        if (empty($validationMethods)) {
            return [];
        }

        $result = [];
        if (is_array($validationMethods)) {
            foreach ($validationMethods as $method) {
                $method = trim($method);
                switch ($method) {
                    case 'cname':
                        $result['cname'] = '解析验证 CNAME';
                        break;
                    case 'txt':
                        $result['txt'] = '解析验证 TXT';
                        break;
                    case 'http':
                        $result['http'] = '文件验证 FILE';
                        break;
                    case 'file':
                        $result['file'] = '文件验证 FILE';
                        break;
                    case 'delegation':
                        $result['delegation'] = '委托验证（自动TXT）';
                        break;
                }
            }
        }

        return $result;
    }

    /**
     * 根据邮箱获取或创建用户ID
     */
    protected function getUserByEmail(string $email, string $from = ''): User
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            $username = Random::build('alpha', 10);
            $password = Random::build('alnum', 10);

            // 创建用户
            $user = User::create([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'level_code' => 'platinum',
                'source' => $from,
                'status' => 1,
                'join_at' => now(),
                'join_ip' => request()->ip(),
            ]);

            // 发送注册通知邮件
            try {
                $siteName = get_system_setting('site', 'name', 'SSL证书管理系统');
                $siteDomain = get_system_setting('site', 'url') ?? $siteName;

                $intent = new NotificationIntent(
                    'user_created',
                    'user',
                    $user->id,
                    [
                        'username' => $username,
                        'password' => $password,
                        'site_name' => $siteName,
                        'site_url' => $siteDomain,
                        'email' => $email,
                    ],
                    ['mail']
                );

                app(NotificationCenter::class)->dispatch($intent);
            } catch (Throwable $e) {
                app(ApiExceptions::class)->logException($e);
            }
        }

        if ($user->level_code === 'standard' || $user->level_code === 'gold') {
            $user->update(['level_code' => 'platinum']);
        }

        return $user;
    }

    /**
     * 验证申请参数
     */
    protected function validateApplyParams(array $params): void
    {
        $rules = [
            'domain' => 'required',
            'email' => 'required|email',
            'validation_method' => 'required|in:file,http,https,cname,txt,delegation',
        ];

        $messages = [
            'domain.required' => '域名不能为空',
            'email.required' => '邮箱不能为空',
            'email.email' => '邮箱格式错误',
            'validation_method.required' => '验证方法不能为空',
            'validation_method.in' => '验证方法错误',
        ];

        $validator = Validator::make($params, $rules, $messages);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            $this->error(implode(' ', $errors));
        }
    }
}
