import { http } from "@/utils/http";

export interface AcmeCert {
  id: number;
  order_id: number;
  last_cert_id: number | null;
  action: "new" | "reissue";
  channel: string;
  api_id: string | null;
  vendor_id: string | null;
  refer_id: string | null;
  common_name: string;
  alternative_names: string | null;
  email: string | null;
  standard_count: number;
  wildcard_count: number;
  validation_method: string | null;
  serial_number: string | null;
  issuer: string | null;
  fingerprint: string | null;
  issued_at: string | null;
  expires_at: string | null;
  status: string;
  created_at: string;
  updated_at: string;
  acme_authorizations?: AcmeAuthorization[];
}

export interface AcmeAuthorization {
  id: number;
  identifier_value: string;
  status: string;
  challenges?: AcmeChallenge[];
}

export interface AcmeChallenge {
  id: number;
  type: string;
  status: string;
}

export interface AcmeOrder {
  id: number;
  user_id: number;
  product_id: number;
  latest_cert_id: number | null;
  brand: string;
  period: number;
  amount: string;
  eab_kid: string | null;
  period_from: string | null;
  period_till: string | null;
  cancelled_at: string | null;
  auto_renew: boolean | null;
  remark: string | null;
  created_at: string;
  updated_at: string;
  product?: { id: number; name: string };
  latest_cert?: AcmeCert;
}

export interface CreateAcmeOrderForm {
  product_id: number | undefined;
  domains: string;
  period: number | string;
  validation_method: string;
}

export interface AcmeOrderParams {
  currentPage?: number;
  pageSize?: number;
  brand?: string;
  status?: string;
}

/** 创建 ACME 订阅订单 */
export function createOrder(data: {
  product_id: number;
  period: number;
  domains: string;
  validation_method: string;
}): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, typeof data>("/acme/order", { data });
}

/** 获取 ACME 订单列表 */
export function getAcmeOrders(params: AcmeOrderParams): Promise<BaseResponse> {
  return http.get<BaseResponse, AcmeOrderParams>("/acme/order", { params });
}

/** 获取 ACME 订单详情 */
export function getAcmeOrderDetail(id: number): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, null>(`/acme/order/${id}`);
}

/** 取消 ACME 订单 */
export function cancelAcmeOrder(id: number): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, null>(`/acme/order/commit-cancel/${id}`);
}
