/**
 * 配置管理模块
 * @version 1.0.0
 */
window.Config = (function () {
  "use strict";

  let config = {
    baseURL: "/api/easy",
    helpURL: "/help",
    dnsTools: [
      "https://dns-tools-us.cnssl.com",
      "https://dns-tools-cn.cnssl.com",
    ],
  };

  // 解析应用基路径（根据当前脚本路径推导 /easy/ 前缀）
  function getAppBase() {
    try {
      const cs = document.currentScript;
      if (cs && cs.src) {
        const u = new URL(cs.src, window.location.href);
        // /easy/js/config.js -> /easy/
        const base = u.pathname.replace(/\/js\/[^/]*$/, "/");
        if (base) return base;
      }
    } catch (_) {}
    // 退化到常见前缀 /easy/
    const m = window.location.pathname.match(/^(.*\/easy\/)/);
    if (m) return m[1];
    return "/";
  }

  // 加载配置文件
  async function loadConfig() {
    try {
      const base = getAppBase();
      const response = await fetch(`${base}config.json?t=${Date.now()}`);
      if (response.ok) {
        const data = await response.json();
        config = Object.assign({}, config, data);
      }
    } catch (error) {}
    return config;
  }

  // 获取配置
  function getConfig(key) {
    if (!key) return config;

    const keys = key.split(".");
    let value = config;

    for (const k of keys) {
      if (value && typeof value === "object" && k in value) {
        value = value[k];
      } else {
        return undefined;
      }
    }

    return value;
  }

  // 获取 DCV 验证端点
  function getDCVEndpoints() {
    const dnsTools = config.dnsTools || [];
    return dnsTools.map((host) => `${host}/api/dcv/verify`);
  }

  return {
    loadConfig,
    getConfig,
    getDCVEndpoints,
  };
})();
