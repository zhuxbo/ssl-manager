<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\UserLevel\GetCodesRequest;
use App\Http\Requests\UserLevel\GetIdsRequest;
use App\Http\Requests\UserLevel\IndexRequest;
use App\Http\Requests\UserLevel\StoreRequest;
use App\Http\Requests\UserLevel\UpdateRequest;
use App\Models\UserLevel;

class UserLevelController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取用户级别列表
     */
    public function index(IndexRequest $request): void
    {
        $validated = $request->validated();
        $currentPage = (int) ($validated['currentPage'] ?? 1);
        $pageSize = (int) ($validated['pageSize'] ?? 10);

        $query = UserLevel::query();

        // 添加搜索条件
        if (! empty($validated['quickSearch'])) {
            $query->where(function ($query) use ($validated) {
                $query->where('code', 'like', "%{$validated['quickSearch']}%")
                    ->orWhere('name', 'like', "%{$validated['quickSearch']}%");
            });
        }

        // 值有可能为0 所以用isset
        if (isset($validated['custom'])) {
            $query->where('custom', $validated['custom']);
        }

        if (! empty($validated['code'])) {
            $query->whereIn('code', explode(',', $validated['code']));
        }

        $total = $query->count();
        $items = $query->orderBy('custom', 'desc')
            ->orderBy('weight', 'asc')
            ->orderBy('id', 'asc')
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
     * 添加用户级别
     */
    public function store(StoreRequest $request): void
    {
        $userLevel = UserLevel::create($request->validated());

        if (! $userLevel->exists) {
            $this->error('添加失败');
        }

        $this->success();
    }

    /**
     * 获取用户级别
     */
    public function show($id): void
    {
        $userLevel = UserLevel::find($id);

        if (! $userLevel) {
            $this->error('用户级别不存在');
        }

        $this->success($userLevel->toArray());
    }

    /**
     * 批量获取用户级别
     */
    public function batchShow(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $userLevels = UserLevel::whereIn('id', $ids)->get();
        if ($userLevels->isEmpty()) {
            $this->error('用户级别不存在');
        }

        $this->success($userLevels->toArray());
    }

    /**
     * 批量获取用户级别
     */
    public function batchShowInCodes(GetCodesRequest $request): void
    {
        $codes = $request->validated('codes');

        $userLevels = UserLevel::whereIn('code', $codes)->get();
        if ($userLevels->isEmpty()) {
            $this->error('用户级别不存在');
        }

        $this->success($userLevels->toArray());
    }

    /**
     * 更新用户级别
     */
    public function update(UpdateRequest $request, $id): void
    {
        $userLevel = UserLevel::find($id);
        if (! $userLevel) {
            $this->error('用户级别不存在');
        }

        $userLevel->fill($request->validated());
        $userLevel->save();

        $this->success();
    }

    /**
     * 删除用户级别
     */
    public function destroy($id): void
    {
        $userLevel = UserLevel::find($id);
        if (! $userLevel) {
            $this->error('用户级别不存在');
        }

        $userLevel->delete();
        $this->success();
    }

    /**
     * 批量删除用户级别
     */
    public function batchDestroy(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $userLevels = UserLevel::whereIn('id', $ids)->get();
        if ($userLevels->isEmpty()) {
            $this->error('用户级别不存在');
        }

        UserLevel::destroy($ids);
        $this->success();
    }
}
