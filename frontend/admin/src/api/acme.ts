import { http } from "@/utils/http";

/** 创建 ACME 订阅订单 */
export function createOrder(data: {
  user_id: number;
  product_id: number;
  period: number;
}): Promise<BaseResponse> {
  return http.post<BaseResponse, any>("/acme/order", { data });
}

/** 获取订单 EAB 信息 */
export function getEab(orderId: number): Promise<BaseResponse> {
  return http.get<BaseResponse, any>(`/acme/eab/${orderId}`);
}
