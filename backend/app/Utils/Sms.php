<?php

namespace App\Utils;

use App\Bootstrap\ApiExceptions;
use App\Traits\ApiResponse;
use Overtrue\EasySms\EasySms;
use Overtrue\EasySms\Exceptions\NoGatewayAvailableException;
use Overtrue\EasySms\Strategies\OrderStrategy;
use Throwable;

/**
 * 短信工具类
 * 基于EasySms封装短信发送功能
 */
class Sms
{
    use ApiResponse;

    /**
     * EasySms实例
     */
    protected EasySms $easySms;

    /**
     * 是否已在管理后台配置好短信服务
     */
    public bool $configured = true;

    /**
     * 系统短信配置
     */
    protected array $systemConfig;

    /**
     * 构造函数
     *
     * @throws Throwable
     */
    public function __construct()
    {
        $this->systemConfig = get_system_setting('sms');

        $config = [
            // HTTP 请求的超时时间（秒）
            'timeout' => 5.0,

            // 默认发送配置
            'default' => [
                // 网关调用策略，默认：顺序调用
                'strategy' => OrderStrategy::class,

                // 默认可用的发送网关
                'gateways' => [$this->systemConfig['gateway']],
            ],

            // 可用的网关配置
            'gateways' => [],
        ];

        // 根据配置添加网关
        $defaultGateway = $this->systemConfig['gateway'] ?? '';

        // 检查配置是否存在
        if (! empty($defaultGateway)) {
            $gatewayConfig = $this->systemConfig[$defaultGateway];

            if (empty($gatewayConfig) || ! is_array($gatewayConfig)) {
                $this->configured = false;
            } else {
                // 根据不同网关类型设置配置
                switch ($defaultGateway) {
                    case 'aliyun':
                        $config['gateways']['aliyun'] = [
                            'access_key_id' => $gatewayConfig['access_key_id'] ?? '',
                            'access_key_secret' => $gatewayConfig['access_key_secret'] ?? '',
                            'sign_name' => $gatewayConfig['sign_name'] ?? '',
                        ];
                        break;
                    case 'tencent':
                        $config['gateways']['tencent'] = [
                            'sdk_app_id' => $gatewayConfig['sdk_app_id'] ?? '',
                            'secret_id' => $gatewayConfig['secret_id'] ?? '',
                            'secret_key' => $gatewayConfig['secret_key'] ?? '',
                            'sign_name' => $gatewayConfig['sign_name'] ?? '',
                        ];
                        break;
                    case 'huawei':
                        $config['gateways']['huawei'] = [
                            'endpoint' => $gatewayConfig['endpoint'] ?? 'https://api.rtc.huaweicloud.com:10443',
                            'app_key' => $gatewayConfig['app_key'] ?? '',
                            'app_secret' => $gatewayConfig['app_secret'] ?? '',
                            'sender' => $gatewayConfig['sender'] ?? '',
                            'signature' => $gatewayConfig['signature'] ?? '',
                        ];
                        break;
                }

                if (empty($config['gateways'][$defaultGateway]) || ! is_array($config['gateways'][$defaultGateway])) {
                    $this->configured = false;
                } else {
                    foreach ($config['gateways'][$defaultGateway] as $value) {
                        if (empty($value)) {
                            $this->configured = false;
                        }
                    }
                }
            }
        }

        $this->easySms = new EasySms($config);
    }

    /**
     * 发送短信
     *
     * @param  string  $mobile  手机号
     * @param  string  $templateType  模板类型，对应配置文件中的模板类型
     * @param  array  $data  模板参数
     * @return array 发送结果
     */
    public function send(string $mobile, string $templateType, array $data = []): array
    {
        if (! $this->configured) {
            return [
                'code' => 0,
                'msg' => '短信服务尚未配置',
            ];
        }

        $defaultGateway = $this->systemConfig['gateway'] ?? '';
        $templateId = $this->systemConfig[$defaultGateway][$templateType.'_template_id'] ?? '';

        if (empty($templateId)) {
            return [
                'code' => 0,
                'msg' => "短信模板未配置: $templateType.$defaultGateway",
            ];
        }

        try {
            $result = $this->easySms->send($mobile, [
                'template' => $templateId,
                'data' => $data,
            ]);

            return [
                'code' => 1,
                'data' => $result,
            ];
        } catch (NoGatewayAvailableException $e) {
            // 记录异常
            app(ApiExceptions::class)->logException($e);

            return [
                'code' => 0,
                'msg' => '短信发送失败,请检查短信网关配置',
            ];
        } catch (Throwable $e) {
            // 记录异常
            app(ApiExceptions::class)->logException($e);

            return [
                'code' => 0,
                'msg' => '短信发送失败3',
            ];
        }
    }
}
