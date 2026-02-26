import { http } from "../http";

export interface IndexParams {
  currentPage?: number;
  pageSize?: number;
  quickSearch?: string;
  platform?: string;
  tid?: string;
  username?: string;
  type?: number;
  recharged?: number;
  created_at?: [string, string];
}

export interface AgisoDetail {
  id: number;
  platform: string;
  sign: string;
  data: string;
  tid: string;
  type: number;
  price: string;
  count: number;
  amount: string;
  user_id: number;
  order_id: number;
  recharged: number;
  timestamp: number;
  created_at: string;
  user?: {
    id: number;
    username: string;
    email: string;
  };
}

export function index(params: IndexParams): Promise<any> {
  return http.get("/agiso", { params });
}

export function show(id: number): Promise<any> {
  return http.get(`/agiso/${id}`);
}

export function destroy(id: number): Promise<any> {
  return http.delete(`/agiso/${id}`);
}

export function batchDestroy(ids: number[]): Promise<any> {
  return http.delete("/agiso", { data: { ids } });
}
