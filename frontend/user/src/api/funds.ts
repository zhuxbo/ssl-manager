import { http } from "@/utils/http";

export interface IndexParams {
  currentPage?: number;
  pageSize?: number;
  id?: number;
  amount?: number[];
  type?: string;
  pay_method?: string;
  pay_sn?: string;
  status?: number;
  created_at?: [string, string];
}

/** 获取资金列表 */
export function index(params: IndexParams): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, IndexParams>("/fund", { params });
}

/** 检查充值状态 */
export function check(id: string): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, { id: string }>(`/fund/check/${id}`);
}

/** 平台充值 - 查询订单状态 */
export function platformRecharge(tid: string | number): Promise<BaseResponse> {
  return http.post<BaseResponse, { tid: string | number }>(
    "/fund/platform-recharge",
    { data: { tid: tid } }
  );
}
