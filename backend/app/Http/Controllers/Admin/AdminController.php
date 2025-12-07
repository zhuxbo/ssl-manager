<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\GetIdsRequest;
use App\Http\Requests\Admin\IndexRequest;
use App\Http\Requests\Admin\StoreRequest;
use App\Http\Requests\Admin\UpdateRequest;
use App\Models\Admin;

class AdminController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取管理员列表
     */
    public function index(IndexRequest $request): void
    {
        $validated = $request->validated();
        $currentPage = (int) ($validated['currentPage'] ?? 1);
        $pageSize = (int) ($validated['pageSize'] ?? 10);

        $query = Admin::query();

        // 添加搜索条件
        if (! empty($validated['quickSearch'])) {
            $query->where(function ($query) use ($validated) {
                $query->where('username', 'like', "%{$validated['quickSearch']}%")
                    ->orWhere('email', 'like', "%{$validated['quickSearch']}%")
                    ->orWhere('mobile', 'like', "%{$validated['quickSearch']}%");
            });
        }
        if (! empty($validated['username'])) {
            $query->where('username', $validated['username']);
        }
        if (! empty($validated['email'])) {
            $query->where('email', 'like', "%{$validated['email']}%");
        }
        if (! empty($validated['mobile'])) {
            $query->where('mobile', 'like', "%{$validated['mobile']}%");
        }
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['created_at'])) {
            $query->whereBetween('created_at', $validated['created_at']);
        }

        // 排除当前管理员
        // $query->where('id', '!=', $this->guard->id());

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')
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
     * 添加管理员
     */
    public function store(StoreRequest $request): void
    {
        $admin = Admin::create($request->validated());

        if (! $admin->exists) {
            $this->error('添加失败');
        }

        $this->success();
    }

    /**
     * 获取管理员资料
     */
    public function show($id): void
    {
        $admin = Admin::find($id);
        if (! $admin) {
            $this->error('管理员不存在');
        }

        $this->success($admin->toArray());
    }

    /**
     * 批量获取管理员资料
     */
    public function batchShow(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $admins = Admin::whereIn('id', $ids)->get();
        if ($admins->isEmpty()) {
            $this->error('管理员不存在');
        }

        $this->success($admins->toArray());
    }

    /**
     * 更新管理员资料
     */
    public function update(UpdateRequest $request, $id): void
    {
        $admin = Admin::find($id);
        if (! $admin) {
            $this->error('管理员不存在');
        }

        if ($admin->id === $this->guard->id()) {
            $this->error('不能修改当前管理员');
        }

        $admin->fill($request->validated());
        $admin->save();

        $this->success();
    }

    /**
     * 删除管理员
     */
    public function destroy($id): void
    {
        $admin = Admin::find($id);
        if (! $admin) {
            $this->error('管理员不存在');
        }

        if ($admin->id === $this->guard->id()) {
            $this->error('不能删除当前管理员');
        }

        $admin->delete();
        $this->success();
    }

    /**
     * 批量删除管理员
     */
    public function batchDestroy(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $admins = Admin::whereIn('id', $ids)->get();
        if ($admins->isEmpty()) {
            $this->error('管理员不存在');
        }

        if ($admins->contains('id', $this->guard->id())) {
            $this->error('不能删除当前管理员');
        }

        Admin::destroy($ids);
        $this->success();
    }
}
