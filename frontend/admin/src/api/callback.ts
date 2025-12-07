import { http } from "@/utils/http";

export interface IndexParams {
  currentPage?: number;
  pageSize?: number;
  username?: string;
  url?: string;
  status?: number;
}

/** 获取回调列表 */
export function index(params: IndexParams): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, IndexParams>("/callback", { params });
}

// 定义 FormParams 的默认值对象
export const FORM_PARAMS_DEFAULT = {
  user_id: 0,
  url: "",
  token: "",
  status: 1
};

// 从默认值对象中提取键
export const FORM_PARAMS_KEYS = Object.keys(
  FORM_PARAMS_DEFAULT
) as (keyof typeof FORM_PARAMS_DEFAULT)[];

// 从默认值对象中提取类型
export type FormParams = {
  [K in keyof typeof FORM_PARAMS_DEFAULT]?: (typeof FORM_PARAMS_DEFAULT)[K];
};

/** 添加回调 */
export function store(data: FormParams): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, FormParams>("/callback", { data });
}

/** 更新回调 */
export function update(id: number, data: FormParams): Promise<BaseResponse> {
  return http.put<BaseResponse<null>, FormParams>(`/callback/${id}`, {
    data
  });
}

/** 获取回调 */
export function show(id: number): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, { id: number }>(`/callback/${id}`);
}

/** 批量获取回调 */
export function batchShow(ids: number[]): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, { ids: number[] }>(`/callback/batch`, {
    params: { ids }
  });
}

/** 删除回调 */
export function destroy(id: number): Promise<BaseResponse> {
  return http.delete<BaseResponse<null>, { id: number }>(`/callback/${id}`);
}

/** 批量删除回调 */
export function batchDestroy(ids: number[]): Promise<BaseResponse> {
  return http.delete<BaseResponse<null>, { ids: number[] }>(`/callback/batch`, {
    data: { ids }
  });
}
