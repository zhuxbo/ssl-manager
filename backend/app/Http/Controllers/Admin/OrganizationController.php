<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Organization\GetIdsRequest;
use App\Http\Requests\Organization\IndexRequest;
use App\Http\Requests\Organization\StoreRequest;
use App\Http\Requests\Organization\UpdateRequest;
use App\Models\Organization;

class OrganizationController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取组织列表
     */
    public function index(IndexRequest $request): void
    {
        $validated = $request->validated();
        $currentPage = (int) ($validated['currentPage'] ?? 1);
        $pageSize = (int) ($validated['pageSize'] ?? 10);

        $query = Organization::query();

        // 添加搜索条件
        if (! empty($validated['quickSearch'])) {
            $query->where(function ($q) use ($validated) {
                $q->whereHas('user', function ($userQuery) use ($validated) {
                    $userQuery->where('username', 'like', "%{$validated['quickSearch']}%");
                })
                    ->orWhere('name', 'like', "%{$validated['quickSearch']}%")
                    ->orWhere('registration_number', 'like', "%{$validated['quickSearch']}%")
                    ->orWhere('phone', 'like', "%{$validated['quickSearch']}%");
            });
        }
        if (! empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }
        if (! empty($validated['username'])) {
            $query->whereHas('user', function ($userQuery) use ($validated) {
                $userQuery->where('username', $validated['username']);
            });
        }
        if (! empty($validated['name'])) {
            $query->where('name', 'like', "%{$validated['name']}%");
        }
        if (! empty($validated['registration_number'])) {
            $query->where('registration_number', 'like', "%{$validated['registration_number']}%");
        }
        if (! empty($validated['country'])) {
            $query->where('country', 'like', "%{$validated['country']}%");
        }
        if (! empty($validated['phone'])) {
            $query->where('phone', 'like', "%{$validated['phone']}%");
        }
        if (! empty($validated['created_at'])) {
            $query->whereBetween('created_at', $validated['created_at']);
        }

        $total = $query->count();
        $items = $query->with([
            'user' => function ($query) {
                $query->select(['id', 'username']);
            },
        ])
            ->select(['id', 'user_id', 'name', 'registration_number', 'country', 'phone', 'created_at'])
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
     * 添加组织
     */
    public function store(StoreRequest $request): void
    {
        $organization = Organization::create($request->validated());

        if (! $organization->exists) {
            $this->error('添加失败');
        }

        $this->success();
    }

    /**
     * 获取组织资料
     */
    public function show($id): void
    {
        $organization = Organization::find($id);
        if (! $organization) {
            $this->error('组织不存在');
        }

        $this->success($organization->toArray());
    }

    /**
     * 批量获取组织资料
     */
    public function batchShow(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $organizations = Organization::whereIn('id', $ids)->get();
        if ($organizations->isEmpty()) {
            $this->error('组织不存在');
        }

        $this->success($organizations->toArray());
    }

    /**
     * 更新组织资料
     */
    public function update(UpdateRequest $request, $id): void
    {
        $organization = Organization::find($id);
        if (! $organization) {
            $this->error('组织不存在');
        }

        $organization->fill($request->validated());
        $organization->save();

        $this->success();
    }

    /**
     * 删除组织
     */
    public function destroy($id): void
    {
        $organization = Organization::find($id);
        if (! $organization) {
            $this->error('组织不存在');
        }

        $organization->delete();
        $this->success();
    }

    /**
     * 批量删除组织
     */
    public function batchDestroy(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $organizations = Organization::whereIn('id', $ids)->get();
        if ($organizations->isEmpty()) {
            $this->error('组织不存在');
        }

        Organization::destroy($ids);
        $this->success();
    }
}
