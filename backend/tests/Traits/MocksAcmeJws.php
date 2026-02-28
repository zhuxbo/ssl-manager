<?php

namespace Tests\Traits;

use App\Http\Middleware\AcmeJwsMiddleware;
use App\Models\Acme\Account;
use Closure;
use Illuminate\Http\Request;

/**
 * 提供 ACME JWS 中间件的 mock 功能，
 * 跳过 JWS 签名验证，直接注入 acme_jws / acme_account / acme_jwk 到请求中。
 */
trait MocksAcmeJws
{
    public ?Account $mockAcmeAccount = null;

    public array $mockAcmeJws = ['payload' => [], 'protected' => []];

    /**
     * 设置 mock ACME 账户
     */
    protected function withAcmeAccount(?Account $account): static
    {
        $this->mockAcmeAccount = $account;

        return $this;
    }

    /**
     * 设置 mock ACME JWS 数据
     */
    protected function withAcmeJws(array $jws): static
    {
        $this->mockAcmeJws = $jws;

        return $this;
    }

    /**
     * 安装 ACME JWS 中间件的 mock，跳过签名验证
     */
    protected function mockAcmeJwsMiddleware(): void
    {
        $trait = $this;

        $this->app->bind(AcmeJwsMiddleware::class, function () use ($trait) {
            return new class($trait) {
                private $trait;

                public function __construct($trait)
                {
                    $this->trait = $trait;
                }

                public function handle(Request $request, Closure $next)
                {
                    $request->attributes->set('acme_jws', $this->trait->mockAcmeJws);
                    $request->attributes->set('acme_account', $this->trait->mockAcmeAccount);
                    $request->attributes->set('acme_jwk', $this->trait->mockAcmeJws['protected']['jwk'] ?? null);

                    return $next($request);
                }
            };
        });
    }
}
