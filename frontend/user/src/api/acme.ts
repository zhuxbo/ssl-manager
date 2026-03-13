import { http } from "@/utils/http";
import { useUserStoreHook } from "@/store/modules/user";

/** 从响应中提取 balance 并同步余额 */
function syncBalance(res: BaseResponse): BaseResponse {
  if (res?.code === 1) {
    const balance = res.data?.balance;
    if (balance != null) {
      useUserStoreHook().updateBalance(String(balance));
    }
  }
  return res;
}

export interface Acme {
  id: number;
  user_id: number;
  product_id: number;
  brand: string;
  period: number;
  amount: string;
  purchased_standard_count: number;
  purchased_wildcard_count: number;
  refer_id: string | null;
  api_id: string | null;
  vendor_id: string | null;
  eab_kid: string | null;
  eab_hmac: string | null;
  period_from: string | null;
  period_till: string | null;
  cancelled_at: string | null;
  status: string;
  remark: string | null;
  created_at: string;
  updated_at: string;
  product?: { id: number; name: string };
}

export interface CreateAcmeForm {
  product_id: number | undefined;
  period: number | string;
  purchased_standard_count: number;
  purchased_wildcard_count: number;
}

export interface AcmeParams {
  currentPage?: number;
  pageSize?: number;
  brand?: string;
  status?: string;
}

/** 创建 ACME 订阅订单 */
export function createOrder(data: {
  product_id: number;
  period: number;
  purchased_standard_count: number;
  purchased_wildcard_count: number;
}): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, typeof data>("/acme/new", { data });
}

/** 支付 ACME 订单 */
export function payOrder(id: number): Promise<BaseResponse> {
  return http
    .post<BaseResponse<null>, null>(`/acme/pay/${id}`)
    .then(syncBalance);
}

/** 提交 ACME 订单 */
export function commitOrder(id: number): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, null>(`/acme/commit/${id}`);
}

/** 获取 ACME 订单列表 */
export function getAcmes(params: AcmeParams): Promise<BaseResponse> {
  return http.get<BaseResponse, AcmeParams>("/acme", { params });
}

/** 获取 ACME 订单详情 */
export function getAcmeDetail(id: number): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, null>(`/acme/${id}`);
}

/** 取消 ACME 订单 */
export function cancelAcme(id: number): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, null>(`/acme/commit-cancel/${id}`);
}
