import { http } from "@/utils/http";

/** 创建 ACME 订阅订单 */
export function createOrder(data: {
  product_id: number;
  period: number;
}): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, any>("/acme/order", { data });
}

/** 获取 EAB 凭据 */
export function getEab(): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, any>("/acme/eab");
}
