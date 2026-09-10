export interface DcvResult {
  domain?: string;
  matched?: string;
  error?: string;
  [key: string]: any;
}

interface DcvResponse {
  data?: { results?: Record<string, DcvResult> };
  errors?: DcvResult[];
}

type DcvItem = { domain: string; [key: string]: any };
type DcvPost = (
  endpoint: string,
  items: DcvItem[],
  timeout: number
) => Promise<DcvResponse>;

/** 外部节点有明确结果即使用；未能检测的项目逐条回落本站，避免一次占用过多后端资源。 */
export async function verifyDcvWithFallback(
  hosts: string[],
  items: DcvItem[],
  post: DcvPost
): Promise<Record<string, DcvResult>> {
  const results: Record<string, DcvResult> = Object.create(null);
  for (const host of hosts) {
    const pending = items.filter(item => !results[item.domain]);
    if (!pending.length) break;
    try {
      const response = await post(`${host}/api/dcv/verify`, pending, 10000);
      const candidates = { ...response.data?.results };
      if (Array.isArray(response.errors)) {
        for (const error of response.errors) {
          if (error.domain) candidates[error.domain] = error;
        }
      }
      for (const item of pending) {
        const result = candidates[item.domain];
        if (result?.matched === "true" || result?.matched === "false") {
          results[item.domain] = result;
        }
      }
    } catch {
      // 节点不可用，继续下一个。
    }
  }

  for (const item of items) {
    if (results[item.domain]) continue;
    let result: DcvResult | undefined;
    try {
      const response = await post("/api/dcv/verify", [item], 15000);
      result = response.data?.results?.[item.domain];
    } catch {
      // 保留不可判定状态，不能沿用上次检测成功结果。
    }
    results[item.domain] = result || {
      matched: "unknown",
      error: "检测服务不可用"
    };
  }
  return results;
}
