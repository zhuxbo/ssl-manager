import { http } from "@/utils/http";

// 任务列表参数类型
export interface IndexParams {
  currentPage?: number;
  pageSize?: number;
  order_id?: number | string;
  action?: string;
  source?: string;
  status?: string;
  created_at?: [string, string];
}

// 获取任务列表
export function index(params: IndexParams): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, IndexParams>("/task", { params });
}

// 获取任务详情
export function show(id: number): Promise<BaseResponse> {
  return http.get<BaseResponse<null>, null>(`/task/${id}`);
}

// 启动任务
export function start(ids: number[]): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, { ids: number[] }>(`/task/batch-start`, {
    data: { ids }
  });
}

// 停止任务
export function stop(ids: number[]): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, { ids: number[] }>(`/task/batch-stop`, {
    data: { ids }
  });
}

// 执行任务
export function execute(ids: number[]): Promise<BaseResponse> {
  return http.post<BaseResponse<null>, { ids: number[] }>(
    `/task/batch-execute`,
    {
      data: { ids }
    }
  );
}

// 删除任务
export function destroy(id: number): Promise<BaseResponse> {
  return http.delete<BaseResponse<null>, { id: number }>(`/task/${id}`);
}

// 批量删除任务
export function batchDestroy(ids: number[]): Promise<BaseResponse> {
  return http.delete<BaseResponse<null>, { ids: number[] }>(`/task/batch`, {
    data: { ids }
  });
}
