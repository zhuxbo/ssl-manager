<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Chain\GetIdsRequest;
use App\Http\Requests\Chain\IndexRequest;
use App\Http\Requests\Chain\StoreRequest;
use App\Http\Requests\Chain\UpdateRequest;
use App\Models\Chain;

class ChainController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取证书链列表
     */
    public function index(IndexRequest $request): void
    {
        $validated = $request->validated();
        $currentPage = (int) ($validated['currentPage'] ?? 1);
        $pageSize = (int) ($validated['pageSize'] ?? 10);

        $query = Chain::query();

        // 添加搜索条件
        if (! empty($validated['common_name'])) {
            $query->where('common_name', 'like', "%{$validated['common_name']}%");
        }

        $total = $query->count();
        $items = $query->select(['id', 'common_name', 'created_at', 'updated_at'])
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
     * 添加证书链
     */
    public function store(StoreRequest $request): void
    {
        $chain = Chain::create($request->validated());

        if (! $chain->exists) {
            $this->error('添加失败');
        }

        $this->success();
    }

    /**
     * 获取证书链资料
     */
    public function show($id): void
    {
        $chain = Chain::find($id);
        if (! $chain) {
            $this->error('证书链不存在');
        }

        $this->success($chain->toArray());
    }

    /**
     * 批量获取证书链资料
     */
    public function batchShow(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $chains = Chain::whereIn('id', $ids)->get();
        if ($chains->isEmpty()) {
            $this->error('证书链不存在');
        }

        $this->success($chains->toArray());
    }

    /**
     * 更新证书链资料
     */
    public function update(UpdateRequest $request, $id): void
    {
        $chain = Chain::find($id);
        if (! $chain) {
            $this->error('证书链不存在');
        }

        $chain->fill($request->validated());
        $chain->save();

        $this->success();
    }

    /**
     * 删除证书链
     */
    public function destroy($id): void
    {
        $chain = Chain::find($id);
        if (! $chain) {
            $this->error('证书链不存在');
        }

        $chain->delete();
        $this->success();
    }

    /**
     * 批量删除证书链
     */
    public function batchDestroy(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $chains = Chain::whereIn('id', $ids)->get();
        if ($chains->isEmpty()) {
            $this->error('证书链不存在');
        }

        Chain::destroy($ids);
        $this->success();
    }
}
