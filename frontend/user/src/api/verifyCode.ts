import { http } from "@/utils/http";

export interface SmsCodeParams {
  mobile: string;
  type: "verify_code" | "register" | "bind" | "reset";
}

/** 发送短信验证码 */
export function sendSmsCode(data: SmsCodeParams) {
  return http.post<BaseResponse<{ expire: number }>, SmsCodeParams>(
    "/send-sms-code",
    {
      data
    }
  );
}

export interface EmailCodeParams {
  email: string;
  type: "verify_code" | "register" | "bind" | "reset";
}

/** 发送邮箱验证码 */
export function sendEmailCode(data: EmailCodeParams) {
  return http.post<BaseResponse<{ expire: number }>, EmailCodeParams>(
    "/send-email-code",
    {
      data
    }
  );
}
