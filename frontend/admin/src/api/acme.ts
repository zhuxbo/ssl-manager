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
  validation: string | null;
  params: string | null;
  amount: string;
  serial_number: string | null;
  issuer: string | null;
  fingerprint: string | null;
  encryption_alg: string | null;
  encryption_bits: number | null;
  signature_digest_alg: string | null;
  cert_apply_status: number;
  domain_verify_status: number;
  issued_at: string | null;
  expires_at: string | null;
  status: string;
  created_at: string;
  updated_at: string;
  intermediate_cert?: string | null;
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
  purchased_standard_count: number;
  purchased_wildcard_count: number;
  eab_kid: string | null;
  eab_used_at: string | null;
  period_from: string | null;
  period_till: string | null;
  cancelled_at: string | null;
  auto_renew: boolean | null;
  auto_reissue: boolean | null;
  admin_remark: string | null;
  remark: string | null;
  created_at: string;
  updated_at: string;
  user?: { id: number; username: string };
  product?: { id: number; name: string };
  latest_cert?: AcmeCert;
}

export interface CreateAcmeOrderForm {
  user_id: number | undefined;
  product_id: number | undefined;
  domains: string;
  period: number | string;
  validation_method: string;
}

export interface AcmeOrderParams {
  currentPage?: number;
  pageSize?: number;
  user_id?: number;
  brand?: string;
  status?: string;
}

export interface AcmeCertParams {
  currentPage?: number;
  pageSize?: number;
  domain?: string;
  status?: string;
  order_id?: number;
}

/** 创建 ACME 订阅订单 */
export function createOrder(data: {
  user_id: number;
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

/** 同步 ACME 订单 */
export function syncAcmeOrder(id: number): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, null>(`/acme/order/sync/${id}`);
}

/** 重新验证 ACME 订单 */
export function revalidateAcmeOrder(id: number): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, null>(`/acme/order/revalidate/${id}`);
}

/** 切换验证方式 */
export function updateAcmeDCV(
  id: number,
  data: { method: string }
): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, { method: string }>(
    `/acme/order/update-dcv/${id}`,
    {
      data
    }
  );
}

/** 取消 ACME 订单 */
export function cancelAcmeOrder(id: number): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, null>(`/acme/order/commit-cancel/${id}`);
}

/** 吊销 ACME 证书 */
export function revokeAcmeOrder(
  id: number,
  data?: { serial_number?: string }
): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, typeof data>(
    `/acme/order/commit-revoke/${id}`,
    {
      data
    }
  );
}

/** 删除 ACME 订单 */
export function deleteAcmeOrder(id: number): Promise<BaseResponse> {
  return http.request<BaseResponse<null>>("delete", `/acme/order/${id}`);
}

/** ACME 订单备注 */
export function remarkAcmeOrder(
  id: number,
  data: { remark: string }
): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, { remark: string }>(
    `/acme/order/remark/${id}`,
    { data }
  );
}

/** 获取 ACME 证书列表 */
export function getAcmeCerts(params: AcmeCertParams): Promise<BaseResponse> {
  return http.get<BaseResponse, AcmeCertParams>("/acme/cert", { params });
}

/** 获取 ACME 证书详情 */
export function getAcmeCertDetail(id: number): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, null>(`/acme/cert/${id}`);
}
