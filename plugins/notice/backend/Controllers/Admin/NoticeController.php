<?php

namespace Plugins\Notice\Controllers\Admin;

use App\Http\Controllers\Admin\BaseController;
use Plugins\Notice\Models\Notice;
use Plugins\Notice\Requests\NoticeStoreRequest;

class NoticeController extends BaseController
{
    public function index(): void
    {
        $query = Notice::query();

        if (request()->filled('is_active')) {
            $query->where('is_active', request()->boolean('is_active'));
        }

        $notices = $query->orderByDesc('sort')->orderByDesc('id')
            ->paginate(request('pageSize', 15));

        $this->success($notices->toArray());
    }

    public function store(NoticeStoreRequest $request): void
    {
        $notice = Notice::create($request->validated());
        $this->success($notice->toArray());
    }

    public function update(NoticeStoreRequest $request, int $id): void
    {
        $notice = Notice::findOrFail($id);
        $notice->update($request->validated());
        $this->success($notice->toArray());
    }

    public function destroy(int $id): void
    {
        Notice::findOrFail($id)->delete();
        $this->success();
    }

    public function toggle(int $id): void
    {
        $notice = Notice::findOrFail($id);
        $notice->is_active = ! $notice->is_active;
        $notice->save();
        $this->success($notice->toArray());
    }
}
