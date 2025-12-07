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
        "Content-Type": "application/json",
      },
      ...options,
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
      body: data,
    });
  }

  // 检查订单状态
  async function check(tid, email) {
    return request("/check", {
      method: "POST",
      body: { tid, email },
    });
  }

  // DCV 验证检测
  async function verifyDCV(validation) {
    const endpoints = Config.getDCVEndpoints();
    let lastError = null;

    // 准备请求数据
    const requestData = {
      domain: validation.domain,
      method: validation.method.toLowerCase(),
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
            "Content-Type": "application/json",
          },
          body: JSON.stringify([requestData]),
        });

        // 只要有响应就返回
        if (response.ok) {
          const data = await response.json();

          // 处理返回数据
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
                link: result.link || result.link_https || result.link_http,
              };
            }
          } else if (data.errors?.length > 0) {
            // 处理错误返回
            const error = data.errors.find(
              (e) => e.domain === validation.domain
            );
            if (error) {
              return {
                checked: error.matched === "true",
                error: error.matched === "false" ? "验证失败" : "",
                detected_value: error.value || error.content || "",
                query: error.query,
                query_sub: error.query_sub,
                value_sub: error.value_sub,
              };
            }
          }

          // 如果没有找到对应域名的结果
          return {
            checked: false,
            error: "未找到验证结果",
          };
        }
      } catch (error) {
        lastError = error;
      }
    }

    // 所有端点都失败
    throw lastError || new Error("验证服务不可用");
  }

  return {
    request,
    apply,
    check,
    verifyDCV,
  };
})();
