<?php

namespace Plugins\Notice\Controllers\User;

use App\Http\Controllers\User\BaseController;
use Plugins\Notice\Models\Notice;

class NoticeController extends BaseController
{
    public function active(): void
    {
        $notices = Notice::where('is_active', true)
            ->orderByDesc('sort')
            ->orderByDesc('id')
            ->select(['id', 'title', 'content', 'type'])
            ->limit(5)
            ->get();

        $this->success($notices->toArray());
    }
}
