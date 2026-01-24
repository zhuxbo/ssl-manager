<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cert\GetIdsRequest;
use App\Http\Requests\Cert\IndexRequest;
use App\Models\Cert;
use Throwable;

class CertController extends Controller
{
    /**
     * 获取证书列表
     *
     * @throws Throwable
     */
    public function index(IndexRequest $request): void
    {
        $validated = $request->validated();
        $currentPage = (int) ($validated['currentPage'] ?? 1);
        $pageSize = (int) ($validated['pageSize'] ?? 10);

        $query = Cert::query();

        if (! empty($validated['order_id'])) {
            $query->where('order_id', $validated['order_id']);
        }
        if (! empty($validated['domain'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('common_name', 'like', "%{$validated['domain']}%")
                    ->orWhere('alternative_names', 'like', "%{$validated['domain']}%");
            });
        }
        if (! empty($validated['issued_at'])) {
            $query->whereBetween('issued_at', $validated['issued_at']);
        }
        if (! empty($validated['expires_at'])) {
            $query->whereBetween('expires_at', $validated['expires_at']);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $total = $query->count();
        $items = $query->select(['id', 'order_id', 'action', 'channel', 'common_name', 'amount', 'status', 'issued_at', 'expires_at'])
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
     * 获取证书资料
     */
    public function show($id): void
    {
        $cert = Cert::find($id);
        if (! $cert) {
            $this->error('证书不存在');
        }

        $this->success($cert->toArray());
    }

    /**
     * 批量获取证书资料
     */
    public function batchShow(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $certs = Cert::whereIn('id', $ids)->get();
        if ($certs->isEmpty()) {
            $this->error('证书不存在');
        }

        $this->success($certs->toArray());
    }
}
