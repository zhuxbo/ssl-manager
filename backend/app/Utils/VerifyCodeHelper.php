<?php

namespace App\Utils;

use App\Bootstrap\ApiExceptions;
use Illuminate\Support\Facades\Cache;
use Throwable;

class VerifyCodeHelper
{
    /**
     * 发送手机验证码
     *
     * @param  string  $mobile  手机号
     * @param  string  $type  验证码类型
     * @return array 发送结果和验证码信息
     *
     * @throws Throwable
     */
    public static function sendSmsCode(string $mobile, string $type = 'verify_code'): array
    {
        $codeCachePrefix = 'verify_code_';
        $codeExpire = get_system_setting('sms', 'expire') ?? 600;

        // 生成验证码
        $code = self::generateCode();
        $codeKey = $codeCachePrefix.$type.'_'.$mobile;

        // 发送验证码
        $sms = new Sms;
        $result = $sms->send($mobile, $type, ['code' => $code]);

        if ($result['code'] === 1) {
            // 保存验证码到缓存
            Cache::put($codeKey, $code, $codeExpire);

            return [
                'code' => 1,
                'data' => null,
            ];
        } else {
            return [
                'code' => 0,
                'msg' => $result['msg'] ?? '短信发送失败',
            ];
        }

    }

    /**
     * 发送邮箱验证码
     *
     * @param  string  $email  邮箱
     * @param  string  $type  验证码类型
     * @return array 发送结果和验证码信息
     */
    public static function sendEmailCode(string $email, string $type = 'verify_code'): array
    {
        $codeCachePrefix = 'verify_code_';
        $codeExpire = get_system_setting('sms', 'expire', 600);
        $siteName = get_system_setting('site', 'name', 'SSL证书管理系统');

        // 生成验证码
        $code = self::generateCode();
        $codeKey = $codeCachePrefix.$type.'_'.$email;

        // 保存验证码到缓存
        Cache::put($codeKey, $code, $codeExpire);

        // 发送验证码邮件
        try {
            $mail = new Email;
            $mail->isSMTP();

            if (! $mail->configured) {
                return [
                    'code' => 0,
                    'msg' => '邮件服务未配置',
                ];
            }

            $mail->addAddress($email);
            $mail->setSubject($siteName.'验证码');

            // 创建邮件内容
            $content = "您的验证码是: {$code}，有效期".($codeExpire / 60).'分钟。';
            if ($type === 'register') {
                $content = "感谢您注册我们的服务，您的验证码是: {$code}，有效期".($codeExpire / 60).'分钟。';
            } elseif ($type === 'bind') {
                $content = "您正在绑定邮箱，验证码是: {$code}，有效期".($codeExpire / 60).'分钟。';
            } elseif ($type === 'reset') {
                $content = "您正在重置密码，验证码是: {$code}，有效期".($codeExpire / 60).'分钟。';
            }

            $mail->Body = $content;
            $mail->send();

            return [
                'code' => 1,
                'data' => null,
            ];
        } catch (Throwable $e) {
            // 记录异常
            app(ApiExceptions::class)->logException($e);

            return [
                'code' => 0,
                'msg' => '邮件发送失败',
            ];
        }
    }

    /**
     * 验证邮箱验证码
     *
     * @param  string  $email  邮箱
     * @param  string  $code  验证码
     * @param  string  $type  验证码类型
     * @param  bool  $autoDelete  是否自动删除验证码
     */
    public static function verifyEmailCode(string $email, string $code, string $type = 'verify_code', bool $autoDelete = true): bool
    {
        $codeCachePrefix = 'verify_code_';
        $cacheKey = $codeCachePrefix.$type.'_'.$email;
        $savedCode = Cache::get($cacheKey);

        if ($savedCode && $savedCode === $code) {
            if ($autoDelete) {
                Cache::forget($cacheKey);
            }

            return true;
        }

        return false;
    }

    /**
     * 验证手机验证码
     *
     * @param  string  $mobile  手机号
     * @param  string  $code  验证码
     * @param  string  $type  验证码类型
     * @param  bool  $autoDelete  是否自动删除验证码
     */
    public static function verifySmsCode(string $mobile, string $code, string $type = 'verify_code', bool $autoDelete = true): bool
    {
        $codeCachePrefix = 'verify_code_';
        $codeKey = $codeCachePrefix.$type.'_'.$mobile;
        $savedCode = Cache::get($codeKey);

        if ($savedCode && $savedCode === $code) {
            if ($autoDelete) {
                Cache::forget($codeKey);
            }

            return true;
        }

        return false;
    }

    /**
     * 生成随机验证码
     */
    protected static function generateCode(): string
    {
        $length = 6;
        $characters = '0123456789';
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $code;
    }
}
