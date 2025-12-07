<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\InvoiceLimit\IndexRequest;
use App\Models\InvoiceLimit;

/**
 * 发票额度
 */
class InvoiceLimitController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取发票额度列表
     */
    public function index(IndexRequest $request): void
    {
        $validated = $request->validated();
        $currentPage = (int) ($validated['currentPage'] ?? 1);
        $pageSize = (int) ($validated['pageSize'] ?? 10);

        $query = InvoiceLimit::query();

        // 添加搜索条件
        if (! empty($validated['quickSearch'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('limit_id', 'like', "%{$validated['quickSearch']}%")
                    ->orWhereHas('user', function ($userQuery) use ($validated) {
                        $userQuery->where('username', 'like', "%{$validated['quickSearch']}%");
                    });
            });
        }
        if (! empty($validated['username'])) {
            $query->whereHas('user', function ($userQuery) use ($validated) {
                $userQuery->where('username', $validated['username']);
            });
        }
        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }
        if (! empty($validated['limit_id'])) {
            $query->where('limit_id', $validated['limit_id']);
        }
        if (! empty($validated['amount'])) {
            if (isset($validated['amount'][0]) && isset($validated['amount'][1])) {
                $query->whereBetween('amount', $validated['amount']);
            } elseif (isset($validated['amount'][0])) {
                $query->where('amount', '>=', $validated['amount'][0]);
            } elseif (isset($validated['amount'][1])) {
                $query->where('amount', '<=', $validated['amount'][1]);
            }
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
                'user_id', 'type', 'limit_id', 'amount', 'limit_before', 'limit_after', 'created_at',
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
}
