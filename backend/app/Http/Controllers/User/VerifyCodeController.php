<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Utils\VerifyCodeHelper;
use Illuminate\Support\Facades\Validator;
use Throwable;

class VerifyCodeController extends Controller
{
    /**
     * 发送验证码
     *
     * @throws Throwable
     */
    public function sendSms(): void
    {
        $request = request();
        $mobile = $request->input('mobile', '');
        $type = $request->input('type', 'verify_code');

        // 验证手机号
        $validator = Validator::make([
            'mobile' => $mobile,
            'type' => $type,
        ], [
            'mobile' => 'required|regex:/^1[3-9]\d{9}$/',
            'type' => 'required|in:verify_code,register,bind,reset',
        ]);

        if ($validator->fails()) {
            $this->error('参数错误', $validator->errors()->toArray());
        }

        // 发送短信验证码
        $result = VerifyCodeHelper::sendSmsCode($mobile, $type);

        if ($result['code'] === 1) {
            $this->success(['expire' => get_system_setting('sms', 'expire') ?? 600]);
        } else {
            $this->error($result['msg'] ?? '短信发送失败，请稍后再试');
        }
    }

    /**
     * 发送邮箱验证码
     *
     * @throws Throwable
     */
    public function sendEmail(): void
    {
        $request = request();
        $email = $request->input('email', '');
        $type = $request->input('type', 'verify_code');

        // 验证邮箱
        $validator = Validator::make([
            'email' => $email,
            'type' => $type,
        ], [
            'email' => 'required|email',
            'type' => 'required|in:verify_code,register,bind,reset',
        ]);

        if ($validator->fails()) {
            $this->error('参数错误', $validator->errors()->toArray());
        }

        // 发送邮箱验证码
        $result = VerifyCodeHelper::sendEmailCode($email, $type);

        if ($result['code'] === 1) {
            $this->success(['expire' => get_system_setting('sms', 'expire') ?? 600]);
        } else {
            $this->error($result['msg'] ?? '邮件发送失败，请稍后再试');
        }
    }
}
