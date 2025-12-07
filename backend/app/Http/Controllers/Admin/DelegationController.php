<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Delegation\GetIdsRequest;
use App\Http\Requests\Delegation\IndexRequest;
use App\Http\Requests\Delegation\StoreRequest;
use App\Http\Requests\Delegation\UpdateRequest;
use App\Models\CnameDelegation;
use App\Services\Delegation\CnameDelegationService;
use Throwable;

/**
 * 管理端 CNAME 委托管理控制器
 */
class DelegationController extends BaseController
{
    protected CnameDelegationService $delegationService;

    public function __construct()
    {
        parent::__construct();
        $this->delegationService = new CnameDelegationService;
    }

    /**
     * 获取委托列表
     */
    public function index(IndexRequest $request): void
    {
        $validated = $request->validated();

        $currentPage = (int) ($validated['currentPage'] ?? 1);
        $pageSize = (int) ($validated['pageSize'] ?? 20);

        $query = CnameDelegation::query();

        // 添加快速搜索条件
        if (! empty($validated['quickSearch'])) {
            $query->where(function ($q) use ($validated) {
                $q->whereHas('user', function ($userQuery) use ($validated) {
                    $userQuery->where('username', 'like', "%{$validated['quickSearch']}%")
                        ->orWhere('email', 'like', "%{$validated['quickSearch']}%");
                })
                    ->orWhere('zone', 'like', "%{$validated['quickSearch']}%")
                    ->orWhere('label', 'like', "%{$validated['quickSearch']}%");
            });
        }

        // 添加筛选条件
        if (isset($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }
        if (isset($validated['zone'])) {
            $query->where('zone', 'like', "%{$validated['zone']}%");
        }
        if (isset($validated['prefix'])) {
            $query->where('prefix', $validated['prefix']);
        }
        if (isset($validated['valid'])) {
            $query->where('valid', $validated['valid']);
        }

        $total = $query->count();
        $items = $query
            ->with('user:id,email,username')
            ->orderBy('id', 'desc')
            ->offset(($currentPage - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        // 为每个项目添加 CNAME 配置指引
        $items = $items->map(function ($item) {
            return $this->delegationService->withCnameGuide($item);
        });

        $this->success([
            'items' => $items,
            'total' => $total,
            'pageSize' => $pageSize,
            'currentPage' => $currentPage,
        ]);
    }

    /**
     * 创建委托（管理员为用户创建）
     */
    public function store(StoreRequest $request): void
    {
        $validated = $request->validated();

        try {
            $delegation = $this->delegationService->createOrGet(
                $validated['user_id'],
                $validated['zone'],
                $validated['prefix']
            );

            $data = $this->delegationService->withCnameGuide($delegation);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }

        $this->success($data);
    }

    /**
     * 获取委托详情
     */
    public function show($id): void
    {
        $delegation = CnameDelegation::find((int) $id);
        if (! $delegation) {
            $this->error('委托记录不存在');
        }

        $data = $this->delegationService->withCnameGuide($delegation);

        $this->success($data);
    }

    /**
     * 批量获取委托详情
     */
    public function batchShow(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $delegations = CnameDelegation::whereIn('id', $ids)->get();
        if ($delegations->isEmpty()) {
            $this->error('委托记录不存在');
        }

        $data = $delegations->map(function ($item) {
            return $this->delegationService->withCnameGuide($item);
        });

        $this->success($data->toArray());
    }

    /**
     * 更新委托
     */
    public function update(UpdateRequest $request, $id): void
    {
        $validated = $request->validated();

        try {
            $delegation = CnameDelegation::findOrFail((int) $id);

            $delegation = $this->delegationService->update(
                $delegation->user_id,
                (int) $id,
                $validated
            );

            $data = $this->delegationService->withCnameGuide($delegation);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }

        $this->success($data);
    }

    /**
     * 删除委托
     */
    public function destroy($id): void
    {
        $delegation = CnameDelegation::find((int) $id);

        if (! $delegation) {
            $this->error('委托记录不存在');
        }

        $delegation->delete();

        $this->success();
    }

    /**
     * 批量删除委托
     */
    public function batchDestroy(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $delegations = CnameDelegation::whereIn('id', $ids)->get();

        if ($delegations->isEmpty()) {
            $this->error('委托记录不存在');
        }

        CnameDelegation::destroy($ids);

        $this->success();
    }

    /**
     * 手动触发健康检查
     */
    public function check($id): void
    {
        $delegation = CnameDelegation::find((int) $id);

        if (! $delegation) {
            $this->error('委托记录不存在');
        }

        $valid = $this->delegationService->checkAndUpdateValidity($delegation);

        if ($valid) {
            $data = $this->delegationService->withCnameGuide($delegation->fresh());
            $this->success($data);
        } else {
            $this->error('检查失败：'.$delegation->last_error);
        }
    }
}
