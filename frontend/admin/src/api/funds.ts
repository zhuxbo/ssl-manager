import { http } from "@/utils/http";

export interface IndexParams {
  currentPage?: number;
  pageSize?: number;
  quickSearch?: string;
  id?: number;
  username?: string;
  amount?: number[];
  type?: string;
  pay_method?: string;
  pay_sn?: string;
  status?: number;
  created_at?: [string, string];
}

// 定义 FormParams 的默认值对象
export const FORM_PARAMS_DEFAULT = {
  user_id: 0,
  amount: 0,
  type: "",
  pay_method: "",
  pay_sn: "",
  remark: "",
  status: 0
};

// 从默认值对象中提取键
export const FORM_PARAMS_KEYS = Object.keys(
  FORM_PARAMS_DEFAULT
) as (keyof typeof FORM_PARAMS_DEFAULT)[];

// 从默认值对象中提取类型
export type FormParams = {
  [K in keyof typeof FORM_PARAMS_DEFAULT]?: (typeof FORM_PARAMS_DEFAULT)[K];
};

/** 获取资金列表 */
export function index(params: IndexParams): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, IndexParams>("/fund", { params });
}

/** 添加资金记录 */
export function store(data: FormParams): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, FormParams>("/fund", { data });
}

/** 更新资金记录 */
export function update(
  id: number,
  data: Partial<FormParams>
): Promise<BaseResponse> {
  return http.put<BaseResponse<null>, Partial<FormParams>>(`/fund/${id}`, {
    data
  });
}

/** 获取资金记录 */
export function show(id: number): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, { id: number }>(`/fund/${id}`);
}

/** 批量获取资金记录 */
export function batchShow(ids: number[]): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, { ids: number[] }>(`/fund/batch`, {
    params: { ids }
  });
}

/** 删除资金记录 */
export function destroy(id: number): Promise<BaseResponse> {
  return http.delete<BaseResponse<null>, { id: number }>(`/fund/${id}`);
}

/** 批量删除资金记录 */
export function batchDestroy(ids: number[]): Promise<BaseResponse> {
  return http.delete<BaseResponse<null>, { ids: number[] }>("/fund/batch", {
    data: { ids }
  });
}

/** 退款 */
export function refunds(id: number): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, { id: number }>(`/fund/refunds/${id}`);
}

/** 退回 */
export function reverse(id: number): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, { id: number }>(`/fund/reverse/${id}`);
}

/** 检查充值状态 */
export function check(id: number): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, { id: number }>(`/fund/check/${id}`);
}
