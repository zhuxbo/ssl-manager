import { http } from "@/utils/http";
import type { DataInfo } from "@/utils/auth";

export interface LoginParams {
  account: string;
  password: string;
}

/** 登录 */
export function login(data: LoginParams) {
  return http.post<BaseResponse<DataInfo<Date>>, LoginParams>("/login", {
    data
  });
}

export interface RefreshTokenParams {
  refresh_token: string;
}

/** 刷新token */
export function refreshToken(data: RefreshTokenParams) {
  return http.post<BaseResponse<DataInfo<Date>>, RefreshTokenParams>(
    "/refresh-token",
    { data }
  );
}

export interface RegisterParams {
  username: string;
  email: string;
  password: string;
  code: string;
  source?: string;
}

/** 邮箱注册 */
export function register(data: RegisterParams) {
  return http.post<BaseResponse<DataInfo<Date>>, RegisterParams>("/register", {
    data
  });
}

export interface RegisterWithMobileParams {
  username: string;
  mobile: string;
  password: string;
  code: string;
  source?: string;
}

/** 手机号注册 */
export function registerWithMobile(data: RegisterWithMobileParams) {
  return http.post<BaseResponse<DataInfo<Date>>, RegisterWithMobileParams>(
    "/register-with-mobile",
    { data }
  );
}

/** 登出 */
export function logout(data?: RefreshTokenParams) {
  return http.delete<BaseResponse<null>, RefreshTokenParams>("/logout", {
    data
  });
}

/** 获取资料 */
export function getProfile() {
  return http.get<BaseResponse, null>("/me");
}

export interface UsernameParams {
  username: string;
}

/** 更新用户名 */
export function updateUsername(data: UsernameParams) {
  return http.patch<BaseResponse<null>, UsernameParams>("/update-username", {
    data
  });
}

export interface PasswordParams {
  oldPassword: string;
  newPassword: string;
}

/** 修改密码 */
export function updatePassword(data: PasswordParams) {
  return http.patch<BaseResponse<null>, PasswordParams>("/update-password", {
    data
  });
}

export interface EmailParams {
  email: string;
  code: string;
}

/** 绑定邮箱 */
export function bindEmail(data: EmailParams) {
  return http.patch<BaseResponse<null>, EmailParams>("/bind-email", {
    data
  });
}

export interface MobileParams {
  mobile: string;
  code: string;
}

/** 绑定手机 */
export function bindMobile(data: MobileParams) {
  return http.patch<BaseResponse<null>, MobileParams>("/bind-mobile", {
    data
  });
}

export interface ResetPasswordParams {
  email: string;
  code: string;
  password: string;
}

/** 重置密码（忘记密码） */
export function resetPassword(data: ResetPasswordParams) {
  return http.post<BaseResponse<null>, ResetPasswordParams>("/reset-password", {
    data
  });
}
