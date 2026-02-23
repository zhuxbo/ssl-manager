<?php

namespace App\Http\Controllers\Acme\Rfc8555;

use App\Http\Controllers\Controller;
use App\Services\Acme\NonceService;
use Illuminate\Http\JsonResponse;

class DirectoryController extends Controller
{
    public function __construct(
        private NonceService $nonceService
    ) {}

    /**
     * GET /acme/directory
     * ACME 目录端点
     */
    public function index(): JsonResponse
    {
        $baseUrl = rtrim(request()->getSchemeAndHttpHost(), '/');

        return response()->json([
            'newNonce' => "$baseUrl/acme/new-nonce",
            'newAccount' => "$baseUrl/acme/new-acct",
            'newOrder' => "$baseUrl/acme/new-order",
            'revokeCert' => "$baseUrl/acme/revoke-cert",
            'keyChange' => "$baseUrl/acme/key-change",
            'meta' => [
                'termsOfService' => "$baseUrl/terms",
                'website' => $baseUrl,
                'externalAccountRequired' => true,
            ],
        ], 200, [
            'Content-Type' => 'application/json',
        ]);
    }
}
