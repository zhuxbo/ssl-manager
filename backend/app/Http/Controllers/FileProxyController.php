<?php

namespace App\Http\Controllers;

use App\Models\Cert;

class FileProxyController extends Controller
{
    public function acmeChallenge(string $token)
    {
        return $this->findAndRespond($token, request()->getHost());
    }

    public function pkiValidation(string $filename)
    {
        return $this->findAndRespond($filename, request()->getHost());
    }

    private function findAndRespond(string $name, string $domain)
    {
        $content = null;

        $certs = Cert::where('status', 'processing')
            ->whereNotNull('validation')
            ->where(fn ($q) => $q->where('common_name', $domain)->orWhere('alternative_names', 'like', "%$domain%"))
            ->limit(100)
            ->get();

        foreach ($certs as $cert) {
            foreach ($cert->validation as $item) {
                if (($item['name'] ?? '') === $name
                    && strcasecmp($item['domain'] ?? '', $domain) === 0) {
                    $content = $item['content'] ?? '';
                    break 2;
                }
            }
        }

        if ($content === null) {
            abort(404);
        }

        return response($content, 200, ['Content-Type' => 'text/plain']);
    }
}
