export interface DcvResult {
  domain?: string;
  matched?: string;
  error?: string;
  [key: string]: any;
}

interface DcvResponse {
  msg?: string;
  data?: { results?: Record<string, DcvResult> };
  errors?: DcvResult[];
}

interface DnsResponse {
  code?: number;
  msg?: string;
  data?: { records?: any[] };
}

type DcvItem = { domain: string; [key: string]: any };
type DcvPost = (
  endpoint: string,
  items: DcvItem[],
  timeout: number
) => Promise<DcvResponse>;

function readDcvResults(response: DcvResponse, items: DcvItem[]) {
  const results: Record<string, DcvResult> = Object.create(null);
  const candidates = { ...response?.data?.results };
  if (Array.isArray(response?.errors)) {
    for (const error of response.errors) {
      if (error?.domain) candidates[error.domain] = error;
    }
  }
  for (const item of items) {
    const result = candidates[item.domain];
    const matched =
      result?.matched === "true" || result?.matched === "false"
        ? result.matched
        : "unknown";
    results[item.domain] = {
      ...result,
      matched,
      error:
        result?.error ||
        (matched === "unknown" ? response?.msg || "未获取到验证结果" : "")
    };
  }
  return results;
}

/** 只有请求失败才切换节点；业务失败、unknown 和缺少结果均直接展示，不再回落。 */
export async function verifyDcvWithFallback(
  hosts: string[],
  items: DcvItem[],
  post: DcvPost
): Promise<Record<string, DcvResult>> {
  if (!items.length) return {};
  for (const host of hosts) {
    let response: DcvResponse;
    try {
      response = await post(`${host}/api/dcv/verify`, items, 10000);
    } catch {
      continue;
    }
    return readDcvResults(response, items);
  }

  const results: Record<string, DcvResult> = Object.create(null);
  for (const item of items) {
    let response: DcvResponse;
    try {
      response = await post("/api/dcv/verify", [item], 15000);
    } catch {
      results[item.domain] = { matched: "unknown", error: "检测服务不可用" };
      continue;
    }
    Object.assign(results, readDcvResults(response, [item]));
  }
  return results;
}

/** 正常 HTTP 响应即终止，包含业务错误、空记录及缺少记录结构的响应。 */
export async function queryDnsWithFallback(
  hosts: string[],
  data: { domain: string; type: "TXT" | "CNAME" },
  post: (
    endpoint: string,
    data: { domain: string; type: "TXT" | "CNAME" },
    timeout: number
  ) => Promise<DnsResponse>
): Promise<DnsResponse> {
  for (const host of [...hosts, ""]) {
    try {
      return await post(`${host}/api/dns/query`, data, 10000);
    } catch {
      // 仅请求失败才尝试下一节点。
    }
  }
  throw new Error("检测服务不可用");
}
