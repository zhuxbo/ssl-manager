import { http } from "@/utils/http";

// 版本信息类型
export interface VersionInfo {
  version: string;
  name: string;
  build_time: string;
  build_commit: string;
  channel: string;
}

// 更新检查结果类型
export interface UpdateCheckResult {
  has_update: boolean;
  current_version: string;
  latest_version: string | null;
  changelog: string;
  download_url: string | null;
  package_size: string;
  release_date: string;
  channel: string;
}

// Release 信息类型
export interface ReleaseInfo {
  version: string;
  tag_name: string;
  name: string;
  body: string;
  prerelease: boolean;
  created_at: string;
  published_at: string;
  assets: Array<{
    name: string;
    size: number;
    browser_download_url: string;
  }>;
}

// 备份信息类型
export interface BackupInfo {
  id: string;
  version: string;
  created_at: string;
  size: number;
  includes: {
    backend: boolean;
    database: boolean;
    frontend: boolean;
  };
}

// 升级步骤类型
export interface UpgradeStep {
  step: string;
  status: "pending" | "running" | "completed" | "failed";
  error?: string;
  backup_id?: string;
}

// 升级结果类型
export interface UpgradeResult {
  success: boolean;
  from_version?: string;
  to_version?: string;
  backup_id?: string;
  steps: UpgradeStep[];
  error?: string;
}

// 获取当前版本信息
export function getVersion(): Promise<BaseResponse<VersionInfo>> {
  return http.request<BaseResponse<VersionInfo>>("get", "/upgrade/version");
}

// 检查更新
export function checkUpdate(): Promise<BaseResponse<UpdateCheckResult>> {
  return http.request<BaseResponse<UpdateCheckResult>>("get", "/upgrade/check");
}

// 获取历史版本列表
export function getReleases(
  limit: number = 10
): Promise<
  BaseResponse<{ releases: ReleaseInfo[]; current_version: string }>
> {
  return http.request<
    BaseResponse<{ releases: ReleaseInfo[]; current_version: string }>
  >("get", "/upgrade/releases", { params: { limit } });
}

// 执行升级
export function executeUpgrade(
  version: string = "latest"
): Promise<BaseResponse<UpgradeResult>> {
  return http.request<BaseResponse<UpgradeResult>>("post", "/upgrade/execute", {
    data: { version }
  });
}

// 获取备份列表
export function getBackups(): Promise<BaseResponse<{ backups: BackupInfo[] }>> {
  return http.request<BaseResponse<{ backups: BackupInfo[] }>>(
    "get",
    "/upgrade/backups"
  );
}

// 执行回滚
export function executeRollback(
  backupId: string
): Promise<
  BaseResponse<{ success: boolean; backup_id: string; restored_version: string }>
> {
  return http.request<
    BaseResponse<{
      success: boolean;
      backup_id: string;
      restored_version: string;
    }>
  >("post", "/upgrade/rollback", { data: { backup_id: backupId } });
}

// 删除备份
export function deleteBackup(
  backupId: string
): Promise<BaseResponse<{ deleted: boolean }>> {
  return http.request<BaseResponse<{ deleted: boolean }>>(
    "delete",
    "/upgrade/backup",
    { data: { backup_id: backupId } }
  );
}
