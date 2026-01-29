import { http } from "@/utils/http";

export interface IndexParams {
  currentPage?: number;
  pageSize?: number;
  username?: string;
  status?: number;
}

/** 获取部署令牌列表 */
export function index(params: IndexParams): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, IndexParams>("/deploy-token", { params });
}

// 定义 FormParams 的默认值对象
export const FORM_PARAMS_DEFAULT = {
  user_id: 0,
  token: "",
  allowed_ips: [],
  rate_limit: 100,
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

/** 添加部署令牌 */
export function store(data: FormParams): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, FormParams>("/deploy-token", { data });
}

/** 更新部署令牌 */
export function update(id: number, data: FormParams): Promise<BaseResponse> {
  return http.put<BaseResponse<null>, FormParams>(`/deploy-token/${id}`, {
    data
  });
}

/** 获取部署令牌 */
export function show(id: number): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, { id: number }>(`/deploy-token/${id}`);
}

/** 批量获取部署令牌 */
export function batchShow(ids: number[]): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, { ids: number[] }>(
    `/deploy-token/batch`,
    {
      params: { ids }
    }
  );
}

/** 删除部署令牌 */
export function destroy(id: number): Promise<BaseResponse> {
  return http.delete<BaseResponse<null>, { id: number }>(`/deploy-token/${id}`);
}

/** 批量删除部署令牌 */
export function batchDestroy(ids: number[]): Promise<BaseResponse> {
  return http.delete<BaseResponse<null>, { ids: number[] }>(
    `/deploy-token/batch`,
    {
      data: { ids }
    }
  );
}
