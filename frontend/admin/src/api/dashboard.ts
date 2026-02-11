import { http } from "@/utils/http";
import type {
  AdminDashboardOverview,
  SystemOverviewData,
  RealtimeData,
  TrendDataPoint,
  TopProduct,
  BrandStats,
  UserLevelDistribution,
  FinanceOverviewData
} from "@/types/dashboard";

// 获取管理端Dashboard总览数据
export function getAdminDashboardOverview(): Promise<
  BaseResponse<AdminDashboardOverview>
> {
  return http.get<BaseResponse<AdminDashboardOverview>, null>(
    "/dashboard/overview"
  );
}

// 获取系统概览统计
export function getSystemOverview(): Promise<BaseResponse<SystemOverviewData>> {
  return http.get<BaseResponse<SystemOverviewData>, null>(
    "/dashboard/system-overview"
  );
}

// 获取实时统计数据
export function getRealtimeData(): Promise<BaseResponse<RealtimeData>> {
  return http.get<BaseResponse<RealtimeData>, null>("/dashboard/realtime");
}

// 获取趋势数据
export function getTrendsData(
  days = 30,
  type = "daily"
): Promise<BaseResponse<TrendDataPoint[]>> {
  return http.get<BaseResponse<TrendDataPoint[]>, null>("/dashboard/trends", {
    params: { days, type }
  });
}

// 获取产品销售排行
export function getTopProducts(
  days = 30,
  limit = 10
): Promise<BaseResponse<TopProduct[]>> {
  return http.get<BaseResponse<TopProduct[]>, null>("/dashboard/top-products", {
    params: { days, limit }
  });
}

// 获取CA品牌统计
export function getBrandStats(days = 30): Promise<BaseResponse<BrandStats[]>> {
  return http.get<BaseResponse<BrandStats[]>, null>("/dashboard/brand-stats", {
    params: { days }
  });
}

// 获取用户等级分布
export function getUserLevelDistribution(): Promise<
  BaseResponse<UserLevelDistribution[]>
> {
  return http.get<BaseResponse<UserLevelDistribution[]>, null>(
    "/dashboard/user-level-distribution"
  );
}

// 获取财务概览
export function getFinanceOverview(): Promise<
  BaseResponse<FinanceOverviewData>
> {
  return http.get<BaseResponse<FinanceOverviewData>, null>(
    "/dashboard/finance-overview"
  );
}

// 清除Dashboard缓存
export function clearDashboardCache(): Promise<BaseResponse<null>> {
  return http.post<BaseResponse<null>, null>("/dashboard/clear-cache");
}
