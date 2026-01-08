<?php

namespace App\Http\Controllers\Acme\Rfc8555;

use App\Http\Controllers\Controller;
use App\Services\Acme\NonceService;
use Illuminate\Http\Response;

class NonceController extends Controller
{
    public function __construct(
        private NonceService $nonceService
    ) {}

    /**
     * HEAD /acme/new-nonce
     * GET /acme/new-nonce
     * 获取新的 Nonce
     */
    public function newNonce(): Response
    {
        $nonce = $this->nonceService->generate();

        return response('', 200, [
            'Replay-Nonce' => $nonce,
            'Cache-Control' => 'no-store',
        ]);
    }
}
