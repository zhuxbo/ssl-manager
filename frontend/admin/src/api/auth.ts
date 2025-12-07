import { http } from "@/utils/http";
import type { DataInfo } from "@/utils/auth";

export interface LoginParams {
  username: string;
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

export interface ProfileParams {
  email: string;
  mobile: string;
}

/** 更新资料 */
export function updateProfile(data: ProfileParams) {
  return http.patch<BaseResponse<null>, ProfileParams>("/update-profile", {
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
