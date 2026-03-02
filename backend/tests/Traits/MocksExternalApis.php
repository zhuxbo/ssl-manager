<?php

namespace Tests\Traits;

use App\Services\Acme\ApiClient;
use App\Services\Delegation\DelegationDnsService;
use App\Services\Order\Api\default\Sdk;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;

/**
 * 外部 API Mock 辅助
 *
 * 统一 Mock CA SDK / DNS API / 邮件发送等外部依赖
 */
trait MocksExternalApis
{
    /**
     * Mock CA SDK
     *
     * @param  string  $sdkClass  SDK 类名，默认为 Sdk::class
     * @param  array  $methods  需要 Mock 的方法及返回值，格式 ['method' => $returnValue]
     */
    protected function mockSdk(string $sdkClass = Sdk::class, array $methods = []): MockInterface
    {
        $mock = Mockery::mock($sdkClass);

        foreach ($methods as $method => $returnValue) {
            $mock->shouldReceive($method)
                ->andReturn($returnValue);
        }

        // 未指定的方法默认返回空数组
        $mock->shouldReceive(Mockery::any())
            ->byDefault()
            ->andReturn([]);

        $this->app->instance($sdkClass, $mock);

        return $mock;
    }

    /**
     * Mock DNS API（委托验证服务）
     */
    protected function mockDnsApi(): MockInterface
    {
        $mock = Mockery::mock(DelegationDnsService::class);

        $mock->shouldReceive('createRecord')->andReturn(true);
        $mock->shouldReceive('deleteRecord')->andReturn(true);
        $mock->shouldReceive('getRecords')->andReturn([]);

        $this->app->instance(DelegationDnsService::class, $mock);

        return $mock;
    }

    /**
     * Mock ACME ApiClient（上游 ACME REST API）
     */
    protected function mockAcmeClient(array $methods = []): MockInterface
    {
        $mock = Mockery::mock(ApiClient::class);

        foreach ($methods as $method => $returnValue) {
            $mock->shouldReceive($method)
                ->andReturn($returnValue);
        }

        $this->app->instance(ApiClient::class, $mock);

        return $mock;
    }

    /**
     * Mock 邮件发送
     *
     * 使用 Laravel Mail::fake() 阻止真实邮件发送
     */
    protected function mockSmtp(): void
    {
        Mail::fake();
    }
}
