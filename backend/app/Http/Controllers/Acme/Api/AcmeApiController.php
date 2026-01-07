<?php

namespace App\Http\Controllers\Acme\Api;

use App\Http\Controllers\Controller;
use App\Services\Acme\AcmeApiService;
use Illuminate\Http\Request;

class AcmeApiController extends Controller
{
    public function __construct(
        private AcmeApiService $apiService
    ) {}

    /**
     * 创建账户
     * POST /api/acme/accounts
     */
    public function createAccount(Request $request): void
    {
        $request->validate([
            'customer' => 'required|email|max:200',
            'product_code' => 'required|integer',
        ]);

        $result = $this->apiService->createAccount(
            $request->input('customer'),
            $request->integer('product_code')
        );

        if ($result['code'] === 1) {
            $this->success($result['data']);
        } else {
            $this->error($result['msg']);
        }
    }

    /**
     * 获取账户信息
     * GET /api/acme/accounts/{id}
     */
    public function getAccount(int $id): void
    {
        $result = $this->apiService->getAccount($id);

        if ($result['code'] === 1) {
            $this->success($result['data']);
        } else {
            $this->error($result['msg']);
        }
    }

    /**
     * 创建订单
     * POST /api/acme/orders
     */
    public function createOrder(Request $request): void
    {
        $request->validate([
            'account_id' => 'required|integer',
            'domains' => 'required|array|min:1',
            'domains.*' => 'required|string|max:255',
            'product_code' => 'required|string|max:50',
        ]);

        $result = $this->apiService->createOrder(
            $request->integer('account_id'),
            $request->input('domains'),
            $request->input('product_code')
        );

        if ($result['code'] === 1) {
            $this->success($result['data']);
        } else {
            $this->error($result['msg']);
        }
    }

    /**
     * 获取订单详情
     * GET /api/acme/orders/{id}
     */
    public function getOrder(int $id): void
    {
        $result = $this->apiService->getOrder($id);

        if ($result['code'] === 1) {
            $this->success($result['data']);
        } else {
            $this->error($result['msg']);
        }
    }

    /**
     * 获取订单的授权列表
     * GET /api/acme/orders/{id}/authorizations
     */
    public function getOrderAuthorizations(int $id): void
    {
        $result = $this->apiService->getOrderAuthorizations($id);

        if ($result['code'] === 1) {
            $this->success($result['data']);
        } else {
            $this->error($result['msg']);
        }
    }

    /**
     * 完成订单（提交 CSR）
     * POST /api/acme/orders/{id}/finalize
     */
    public function finalizeOrder(Request $request, int $id): void
    {
        $request->validate([
            'csr' => 'required|string',
        ]);

        $result = $this->apiService->finalizeOrder($id, $request->input('csr'));

        if ($result['code'] === 1) {
            $this->success($result['data']);
        } else {
            $this->error($result['msg']);
        }
    }

    /**
     * 下载证书
     * GET /api/acme/orders/{id}/certificate
     */
    public function getCertificate(int $id): void
    {
        $result = $this->apiService->getCertificate($id);

        if ($result['code'] === 1) {
            $this->success($result['data']);
        } else {
            $this->error($result['msg']);
        }
    }

    /**
     * 响应验证挑战
     * POST /api/acme/challenges/{id}/respond
     */
    public function respondToChallenge(int $id): void
    {
        $result = $this->apiService->respondToChallenge($id);

        if ($result['code'] === 1) {
            $this->success($result['data']);
        } else {
            $this->error($result['msg']);
        }
    }

    /**
     * 吊销证书
     * POST /api/acme/certificates/{id}/revoke
     */
    public function revokeCertificate(Request $request, int $id): void
    {
        $request->validate([
            'reason' => 'nullable|string|in:UNSPECIFIED,KEY_COMPROMISE,AFFILIATION_CHANGED,CESSATION_OF_OPERATION,SUPERSEDED',
        ]);

        $result = $this->apiService->revokeCertificate(
            $id,
            $request->input('reason', 'UNSPECIFIED')
        );

        if ($result['code'] === 1) {
            $this->success();
        } else {
            $this->error($result['msg']);
        }
    }
}
