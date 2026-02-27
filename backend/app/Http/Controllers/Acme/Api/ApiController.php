<?php

namespace App\Http\Controllers\Acme\Api;

use App\Http\Controllers\Controller;
use App\Services\Acme\ApiService;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    public function __construct(
        private ApiService $apiService
    ) {}

    /**
     * 创建订单
     * POST /api/acme/orders
     */
    public function createOrder(Request $request): void
    {
        $request->validate([
            'customer' => 'required|email|max:200',
            'product_code' => 'required|string|max:50',
            'domains' => 'required|array|min:1',
            'domains.*' => 'required|string|max:255',
            'refer_id' => 'sometimes|string|max:64',
        ]);

        $result = $this->apiService->createOrder(
            $request->input('customer'),
            $request->input('product_code'),
            $request->input('domains'),
            $request->input('refer_id')
        );

        if ($result['code'] === 1) {
            $this->success($result['data']);
        } else {
            $this->error($result['msg']);
        }
    }

    /**
     * 重签订单
     * POST /api/acme/orders/reissue/{id}
     */
    public function reissueOrder(Request $request, int $id): void
    {
        $request->validate([
            'domains' => 'required|array|min:1',
            'domains.*' => 'required|string|max:255',
            'refer_id' => 'sometimes|string|max:64',
        ]);

        $result = $this->apiService->reissueOrder(
            $id,
            $request->input('domains'),
            $request->input('refer_id')
        );

        if ($result['code'] === 1) {
            $this->success($result['data']);
        } else {
            $this->error($result['msg']);
        }
    }

    /**
     * 取消订单
     * DELETE /api/acme/orders/{id}
     */
    public function cancelOrder(int $id): void
    {
        $result = $this->apiService->cancelOrder($id);

        if ($result['code'] === 1) {
            $this->success();
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
     * GET /api/acme/orders/authorizations/{id}
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
     * POST /api/acme/orders/finalize/{id}
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
     * GET /api/acme/orders/certificate/{id}
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
     * POST /api/acme/challenges/respond/{id}
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
     * POST /api/acme/certificates/revoke
     */
    public function revokeCertificate(Request $request): void
    {
        $request->validate([
            'serial_number' => 'required|string',
            'reason' => 'nullable|string|in:UNSPECIFIED,KEY_COMPROMISE,AFFILIATION_CHANGED,CESSATION_OF_OPERATION,SUPERSEDED',
        ]);

        $result = $this->apiService->revokeCertificate(
            $request->input('serial_number'),
            $request->input('reason', 'UNSPECIFIED')
        );

        if ($result['code'] === 1) {
            $this->success();
        } else {
            $this->error($result['msg']);
        }
    }
}
