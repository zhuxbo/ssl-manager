<?php

namespace App\Http\Controllers\User;

use App\Http\Requests\Transaction\IndexRequest;
use App\Models\Transaction;

/**
 * 交易记录
 */
class TransactionController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取交易记录列表
     */
    public function index(IndexRequest $request): void
    {
        $validated = $request->validated();
        $currentPage = (int) ($validated['currentPage'] ?? 1);
        $pageSize = (int) ($validated['pageSize'] ?? 10);

        $query = Transaction::query();

        // 添加搜索条件
        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }
        if (! empty($validated['transaction_id'])) {
            $query->where('transaction_id', 'like', "%{$validated['transaction_id']}%");
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
        $items = $query->select([
            'type', 'transaction_id', 'amount', 'balance_before', 'balance_after', 'remark', 'created_at',
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
