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

  // DCV 验证检测
  async function verifyDCV(validation) {
    const endpoints = Config.getDCVEndpoints();
    let lastError = null;

    // 准备请求数据
    const requestData = {
      domain: validation.domain,
      method: validation.method.toLowerCase()
    };

    // 根据验证方法添加相应字段
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

    // 尝试所有端点
    for (const endpoint of endpoints) {
      try {
        const response = await fetch(endpoint, {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify([requestData])
        });

        if (response.ok) {
          const data = await response.json();

          // code=1: API 处理成功，有验证结果
          if (data.data?.results) {
            const result = data.data.results[validation.domain];
            if (result) {
              return {
                checked: result.matched === "true",
                error: result.matched === "false" ? "验证失败" : "",
                detected_value: result.value || result.content || "",
                query: result.query,
                query_sub: result.query_sub,
                value_sub: result.value_sub,
                link: result.link || result.link_https || result.link_http
              };
            }
          }
          // code=0 或未找到结果，尝试下一端点
        }
      } catch (error) {
        lastError = error;
      }
    }

    // 所有端点都失败
    throw lastError || new Error("验证服务不可用");
  }

  // 委托验证 CNAME 检测
  async function verifyCname(domain, host, expectedTarget) {
    const endpoints = Config.getDCVEndpoints();
    let lastMsg = "";
    for (const endpoint of endpoints) {
      try {
        const response = await fetch(endpoint, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify([
            { domain, method: "cname", host, value: expectedTarget }
          ])
        });
        if (response.ok) {
          const data = await response.json();
          if (data.data?.results) {
            const result = data.data.results[domain] || {};
            return {
              detected_value: result.value || "",
              checked: result.matched === "true",
              error: result.matched === "false" ? "CNAME 记录不匹配" : ""
            };
          }
          if (data.msg)
            lastMsg = data.msg.replace(
              "批量验证失败：部分或全部验证未通过",
              "验证未通过"
            );
        }
      } catch (error) {
        continue;
      }
    }
    return {
      checked: false,
      detected_value: "",
      error: lastMsg || "检测服务不可用"
    };
  }

  // 委托验证 TXT 检测（使用 /api/dns/query 原始查询）
  async function verifyDelegationTxt(targetFqdn, expectedValue) {
    const dnsToolsHosts = Config.getConfig("dnsTools") || [
      "https://dns-tools-cn.cnssl.com",
      "https://dns-tools-us.cnssl.com"
    ];
    const expectedLower = expectedValue.toLowerCase().trim();

    for (const baseUrl of dnsToolsHosts) {
      try {
        const response = await fetch(`${baseUrl}/api/dns/query`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ domain: targetFqdn, type: "TXT" })
        });
        if (!response.ok) continue;
        const data = await response.json();
        if (data.code !== 1) {
          return {
            detected_value: "",
            checked: false,
            error: "未检测到 TXT 记录"
          };
        }

        const records = data.data?.records || [];
        const txtValues = records
          .filter(r => r.type === "TXT" && r.value)
          .map(r => r.value.replace(/^"|"$/g, "").trim());

        if (txtValues.length === 0) {
          return {
            detected_value: "",
            checked: false,
            error: "未检测到 TXT 记录"
          };
        }

        const matched = txtValues.some(v => v.toLowerCase() === expectedLower);
        return {
          detected_value: txtValues.join(", "),
          checked: matched,
          error: matched ? "" : "TXT 记录不匹配"
        };
      } catch (error) {
        continue;
      }
    }
    return { checked: false, detected_value: "", error: "检测服务不可用" };
  }

  // 查询指定 host 的 TXT 记录（用于检测 TXT 与 CNAME 冲突）
  async function queryTxtRecords(host) {
    const dnsToolsHosts = Config.getConfig("dnsTools") || [
      "https://dns-tools-cn.cnssl.com",
      "https://dns-tools-us.cnssl.com"
    ];

    for (const baseUrl of dnsToolsHosts) {
      try {
        const response = await fetch(`${baseUrl}/api/dns/query`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ domain: host, type: "TXT" })
        });
        if (!response.ok) continue;
        const data = await response.json();
        if (data.code !== 1) return [];

        const records = data.data?.records || [];
        return records
          .filter(r => r.type === "TXT" && r.value)
          .map(r => r.value.replace(/^"|"$/g, "").trim());
      } catch (error) {
        continue;
      }
    }
    return [];
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
