<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ApiToken\GetIdsRequest;
use App\Http\Requests\ApiToken\IndexRequest;
use App\Http\Requests\ApiToken\StoreRequest;
use App\Http\Requests\ApiToken\UpdateRequest;
use App\Models\ApiToken;

class ApiTokenController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取接口令牌列表
     */
    public function index(IndexRequest $request): void
    {
        $validated = $request->validated();
        $currentPage = (int) ($validated['currentPage'] ?? 1);
        $pageSize = (int) ($validated['pageSize'] ?? 10);

        $query = ApiToken::query();

        // 添加搜索条件
        if (! empty($validated['username'])) {
            $query->whereHas('user', function ($userQuery) use ($validated) {
                $userQuery->where('username', $validated['username']);
            });
        }
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $total = $query->count();
        $items = $query->with([
            'user' => function ($query) {
                $query->select(['id', 'username']);
            },
        ])
            ->select([
                'id', 'user_id', 'allowed_ips', 'rate_limit', 'status', 'last_used_at', 'last_used_ip', 'created_at',
            ])
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
     * 添加接口令牌
     */
    public function store(StoreRequest $request): void
    {
        $apiToken = ApiToken::create($request->validated());

        if (! $apiToken->exists) {
            $this->error('添加失败');
        }

        $this->success();
    }

    /**
     * 获取接口令牌详情
     */
    public function show($id): void
    {
        $apiToken = ApiToken::find($id);
        if (! $apiToken) {
            $this->error('接口令牌不存在');
        }

        $this->success($apiToken->toArray());
    }

    /**
     * 批量获取接口令牌详情
     */
    public function batchShow(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $apiTokens = ApiToken::whereIn('id', $ids)->get();
        if ($apiTokens->isEmpty()) {
            $this->error('接口令牌不存在');
        }

        $this->success($apiTokens->toArray());
    }

    /**
     * 更新接口令牌信息
     */
    public function update(UpdateRequest $request, $id): void
    {
        $apiToken = ApiToken::find($id);
        if (! $apiToken) {
            $this->error('接口令牌不存在');
        }

        $apiToken->fill($request->validated());
        $apiToken->save();

        $this->success();
    }

    /**
     * 删除接口令牌
     */
    public function destroy($id): void
    {
        $apiToken = ApiToken::find($id);
        if (! $apiToken) {
            $this->error('接口令牌不存在');
        }

        $apiToken->delete();
        $this->success();
    }

    /**
     * 批量删除接口令牌
     */
    public function batchDestroy(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $apiTokens = ApiToken::whereIn('id', $ids)->get();
        if ($apiTokens->isEmpty()) {
            $this->error('接口令牌不存在');
        }

        ApiToken::destroy($ids);
        $this->success();
    }
}
