/**
 * API 调用模块
 * @version 1.0.0
 */
window.API = (function () {
  "use strict";

  // 请求基础方法
  async function request(url, options = {}) {
    const baseURL = Config.getConfig("baseURL") || "/api/easy";
    const defaultOptions = {
      method: "GET",
      headers: {
        "Content-Type": "application/json"
      },
      ...options
    };

    // 如果有 body 且是对象，转换为 JSON
    if (defaultOptions.body && typeof defaultOptions.body === "object") {
      defaultOptions.body = JSON.stringify(defaultOptions.body);
    }

    try {
      const response = await fetch(baseURL + url, defaultOptions);
      let data;
      try {
        data = await response.json();
      } catch (_) {
        throw new Error("服务器响应异常");
      }

      if (data.code === 1) {
        return data;
      } else {
        throw new Error(data.msg || "请求失败");
      }
    } catch (error) {
      if (error.message === "Failed to fetch") {
        throw new Error("网络连接失败");
      }
      throw error;
    }
  }

  // 申请证书
  async function apply(data) {
    return request("/apply", {
      method: "POST",
      body: data
    });
  }

  // 检查订单状态
  async function check(tid, email) {
    return request("/check", {
      method: "POST",
      body: { tid, email }
    });
  }

  // 正常 HTTP 响应立即采用，只有请求失败才切换节点。
  async function requestDetection(endpoints, body) {
    for (const endpoint of endpoints) {
      let response;
      try {
        response = await fetch(endpoint, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          signal: AbortSignal.timeout(15000),
          body: JSON.stringify(body)
        });
      } catch {
        continue;
      }
      if (!response.ok) continue;
      try {
        return await response.json();
      } catch {
        return null;
      }
    }
    throw new Error("检测服务不可用");
  }

  function readDcvResult(data, domain) {
    return (
      data?.data?.results?.[domain] ||
      (Array.isArray(data?.errors)
        ? data.errors.find(error => error?.domain === domain)
        : null)
    );
  }

  // DCV 验证检测
  async function verifyDCV(validation) {
    const requestData = {
      domain: validation.domain,
      method: validation.method.toLowerCase()
    };
    if (["txt", "cname"].includes(requestData.method)) {
      requestData.host = validation.host || "@";
      requestData.value = validation.value;
    } else if (["file", "http", "https"].includes(requestData.method)) {
      const protocol =
        requestData.method === "file" ? "" : requestData.method + ":";
      const name = validation.file_name || validation.name || "";
      requestData.link =
        validation.link ||
        `${protocol}//${validation.domain}/.well-known/pki-validation/${name}`;
      requestData.name = name;
      requestData.content = validation.file_content || validation.content;
    }
    const data = await requestDetection(Config.getDCVEndpoints(), [
      requestData
    ]);
    const result = readDcvResult(data, validation.domain);
    if (!result) throw new Error(data?.msg || "未获取到验证结果");
    return {
      checked: result.matched === "true",
      error:
        result.error ||
        (result.matched === "true"
          ? ""
          : result.matched === "false"
            ? "验证失败"
            : data?.msg || "验证结果无法判定"),
      detected_value: result.value || result.content || "",
      query: result.query,
      query_sub: result.query_sub,
      value_sub: result.value_sub,
      link: result.link || result.link_https || result.link_http
    };
  }

  // 委托验证 CNAME 检测
  async function verifyCname(domain, host, expectedTarget) {
    try {
      const data = await requestDetection(Config.getDCVEndpoints(), [
        { domain, method: "cname", host, value: expectedTarget }
      ]);
      const result = readDcvResult(data, domain);
      return {
        detected_value: result?.value || "",
        checked: result?.matched === "true",
        error:
          result?.error ||
          (result?.matched === "true"
            ? ""
            : result?.matched === "false"
              ? "验证未通过"
              : data?.msg || "验证结果无法判定")
      };
    } catch (error) {
      return { checked: false, detected_value: "", error: error.message };
    }
  }

  // 委托验证 TXT 检测
  async function verifyDelegationTxt(targetFqdn, expectedValue) {
    try {
      const data = await requestDetection(
        Config.getDnsToolsHosts().map(host => `${host}/api/dns/query`),
        { domain: targetFqdn, type: "TXT" }
      );
      if (data?.code !== 1 || !Array.isArray(data.data?.records)) {
        return {
          checked: false,
          detected_value: "",
          error: data?.msg || "未获取到 DNS 查询结果"
        };
      }
      const txtValues = data.data.records
        .filter(r => r?.type === "TXT" && typeof r.value === "string")
        .map(r => r.value.replace(/^"|"$/g, "").trim());
      if (!txtValues.length) {
        return {
          checked: false,
          detected_value: "",
          error: "未检测到 TXT 记录"
        };
      }
      const expectedLower = expectedValue.toLowerCase().trim();
      const matched = txtValues.some(
        value => value.toLowerCase() === expectedLower
      );
      return {
        checked: matched,
        detected_value: txtValues.join(", "),
        error: matched ? "" : "TXT 记录不匹配"
      };
    } catch (error) {
      return { checked: false, detected_value: "", error: error.message };
    }
  }

  // 保留指定主机的直接 TXT 记录，排除 CNAME 链目标。
  async function queryTxtRecords(host) {
    try {
      const data = await requestDetection(
        Config.getDnsToolsHosts().map(baseUrl => `${baseUrl}/api/dns/query`),
        { domain: host, type: "TXT" }
      );
      if (data?.code !== 1 || !Array.isArray(data.data?.records)) return [];
      const normalizedHost = host.toLowerCase().replace(/\.$/, "");
      return data.data.records
        .filter(
          r =>
            r?.type === "TXT" &&
            typeof r.value === "string" &&
            (!r.name ||
              (typeof r.name === "string" &&
                r.name.toLowerCase().replace(/\.$/, "") === normalizedHost))
        )
        .map(r => r.value.replace(/^"|"$/g, "").trim());
    } catch {
      return [];
    }
  }

  return {
    request,
    apply,
    check,
    verifyDCV,
    verifyCname,
    verifyDelegationTxt,
    queryTxtRecords
  };
})();
