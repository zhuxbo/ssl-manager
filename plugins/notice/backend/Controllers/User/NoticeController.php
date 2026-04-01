<?php

namespace Plugins\Notice\Controllers\User;

use App\Http\Controllers\User\BaseController;
use Plugins\Notice\Models\Notice;

class NoticeController extends BaseController
{
    public function active(): void
    {
        $query = Notice::where('is_active', true);

        if (request()->filled('position')) {
            $query->where('position', request('position'));
        }

        $notices = $query->orderByDesc('sort')
            ->orderByDesc('id')
            ->select(['id', 'title', 'content', 'type', 'position'])
            ->limit(5)
            ->get();

        $this->success($notices->toArray());
    }
}
