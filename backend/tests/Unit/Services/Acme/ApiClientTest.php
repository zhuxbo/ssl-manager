<?php

namespace Tests\Unit\Services\Acme;

use App\Services\Acme\ApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ApiClient 纯单元测试（Mock HTTP）
 */
class ApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_request_uses_system_setting_url_and_key(): void
    {
        // 设置 system_settings 模拟
        $this->mockSystemSettings('https://gateway.test/api', 'test-api-key-123');

        Http::fake([
            'gateway.test/api/*' => Http::response(['code' => 1, 'data' => ['id' => 1]], 200),
        ]);

        $client = app(ApiClient::class);
        $result = $client->getAccount(1);

        $this->assertEquals(1, $result['code']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'gateway.test/api')
                && $request->hasHeader('Authorization', 'Bearer test-api-key-123');
        });
    }

    public function test_create_order_sends_correct_payload(): void
    {
        $this->mockSystemSettings('https://gateway.test/api', 'test-key');

        Http::fake([
            'gateway.test/api/*' => Http::response(['code' => 1, 'data' => ['id' => 99]], 200),
        ]);

        $client = app(ApiClient::class);
        $result = $client->createOrder(1, ['example.com', '*.example.com'], 'DV_SSL');

        $this->assertEquals(1, $result['code']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://gateway.test/api/orders'
                && $body['account_id'] === 1
                && $body['domains'] === ['example.com', '*.example.com']
                && $body['product_code'] === 'DV_SSL';
        });
    }

    public function test_respond_to_challenge_sends_correct_endpoint(): void
    {
        $this->mockSystemSettings('https://gateway.test/api', 'test-key');

        Http::fake([
            'gateway.test/api/*' => Http::response(['code' => 1, 'data' => ['status' => 'valid']], 200),
        ]);

        $client = app(ApiClient::class);
        $result = $client->respondToChallenge(42);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://gateway.test/api/challenges/42/respond';
        });
    }

    public function test_finalize_order_sends_csr(): void
    {
        $this->mockSystemSettings('https://gateway.test/api', 'test-key');

        Http::fake([
            'gateway.test/api/*' => Http::response(['code' => 1, 'data' => ['status' => 'valid']], 200),
        ]);

        $client = app(ApiClient::class);
        $result = $client->finalizeOrder(10, 'test-csr-pem');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://gateway.test/api/orders/10/finalize'
                && $request->data()['csr'] === 'test-csr-pem';
        });
    }

    public function test_is_configured_returns_false_when_not_set(): void
    {
        $this->mockSystemSettings('', '');

        $client = app(ApiClient::class);
        $this->assertFalse($client->isConfigured());
    }

    public function test_is_configured_returns_true_when_set(): void
    {
        $this->mockSystemSettings('https://gateway.test/api', 'key');

        $client = app(ApiClient::class);
        $this->assertTrue($client->isConfigured());
    }

    public function test_request_returns_error_when_not_configured(): void
    {
        $this->mockSystemSettings('', '');

        $client = app(ApiClient::class);
        $result = $client->getAccount(1);

        $this->assertEquals(0, $result['code']);
        $this->assertStringContainsString('not configured', $result['msg']);
    }

    public function test_revoke_certificate_sends_serial_number(): void
    {
        $this->mockSystemSettings('https://gateway.test/api', 'test-key');

        Http::fake([
            'gateway.test/api/*' => Http::response(['code' => 1], 200),
        ]);

        $client = app(ApiClient::class);
        $result = $client->revokeCertificate('ABCD1234');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://gateway.test/api/certificates/revoke'
                && $request->data()['serial_number'] === 'ABCD1234'
                && $request->data()['reason'] === 'UNSPECIFIED';
        });
    }

    /**
     * 模拟 system_settings
     */
    private function mockSystemSettings(string $url, string $key): void
    {
        // 因为 ApiClient 构造函数中直接调用 get_system_setting，
        // 我们需要在容器中重新绑定
        $this->app->bind(ApiClient::class, function () use ($url, $key) {
            $client = new ApiClient;

            // 使用反射设置私有属性
            $ref = new \ReflectionClass($client);

            $baseUrlProp = $ref->getProperty('baseUrl');
            $baseUrlProp->setAccessible(true);
            $baseUrlProp->setValue($client, rtrim($url, '/'));

            $apiKeyProp = $ref->getProperty('apiKey');
            $apiKeyProp->setAccessible(true);
            $apiKeyProp->setValue($client, $key);

            return $client;
        });
    }
}
