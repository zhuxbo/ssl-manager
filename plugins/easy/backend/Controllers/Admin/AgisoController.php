<?php

namespace Plugins\Easy\Controllers\Admin;

use App\Http\Controllers\Admin\BaseController;
use Plugins\Easy\Models\Agiso;
use Plugins\Easy\Requests\AgisoGetIdsRequest;
use Plugins\Easy\Requests\AgisoIndexRequest;

class AgisoController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index(AgisoIndexRequest $request): void
    {
        $validated = $request->validated();
        $currentPage = (int) ($validated['currentPage'] ?? 1);
        $pageSize = (int) ($validated['pageSize'] ?? 10);

        $query = Agiso::query();

        if (! empty($validated['quickSearch'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('tid', 'like', "%{$validated['quickSearch']}%")
                    ->orWhere('platform', 'like', "%{$validated['quickSearch']}%")
                    ->orWhere('product_code', 'like', "%{$validated['quickSearch']}%")
                    ->orWhereHas('user', function ($userQuery) use ($validated) {
                        $userQuery->where('username', 'like', "%{$validated['quickSearch']}%");
                    });
            });
        }
        if (! empty($validated['platform'])) {
            $query->where('platform', $validated['platform']);
        }
        if (! empty($validated['order_id'])) {
            $query->where('order_id', 'like', "%{$validated['order_id']}%");
        }
        if (! empty($validated['product_code'])) {
            $query->where('product_code', 'like', "%{$validated['product_code']}%");
        }
        if (! empty($validated['tid'])) {
            $query->where('tid', 'like', "%{$validated['tid']}%");
        }
        if (! empty($validated['username'])) {
            $query->whereHas('user', function ($userQuery) use ($validated) {
                $userQuery->where('username', $validated['username']);
            });
        }
        if (isset($validated['period'])) {
            $query->where('period', $validated['period']);
        }
        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }
        if (isset($validated['recharged'])) {
            $query->where('recharged', $validated['recharged']);
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
            ->select([
                'id', 'platform', 'tid', 'type', 'price', 'count', 'product_code', 'period',
                'amount', 'user_id', 'order_id', 'recharged', 'timestamp', 'created_at',
            ])
            ->orderBy('id', 'desc')
            ->offset(($currentPage - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        $items->each(function ($item) {
            if (! $item->user) {
                $item->setRelation('user', null);
            }
            if (! $item->order) {
                $item->setRelation('order', null);
            }
            if (! $item->product) {
                $item->setRelation('product', null);
            }
        });

        $this->success([
            'items' => $items,
            'total' => $total,
            'pageSize' => $pageSize,
            'currentPage' => $currentPage,
        ]);
    }

    public function show($id): void
    {
        $agiso = Agiso::find($id);

        if (! $agiso) {
            $this->error('阿奇索记录不存在');
        }

        $relations = [];

        if ($agiso->user_id) {
            $relations['user'] = function ($query) {
                $query->select(['id', 'username', 'email']);
            };
        }

        if (! empty($relations)) {
            $agiso->load($relations);
        }

        $this->success($agiso->toArray());
    }

    public function destroy($id): void
    {
        $agiso = Agiso::find($id);
        if (! $agiso) {
            $this->error('阿奇索记录不存在');
        }

        if ($agiso->recharged === 1) {
            $this->error('已充值的记录不能删除');
        }

        $agiso->delete();
        $this->success();
    }

    public function batchDestroy(AgisoGetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $agisos = Agiso::whereIn('id', $ids)->get();
        if ($agisos->isEmpty()) {
            $this->error('阿奇索记录不存在');
        }

        if ($agisos->where('recharged', 1)->count() > 0) {
            $this->error('存在已充值的记录，不能删除');
        }

        Agiso::destroy($ids);
        $this->success();
    }
}
