/**
 * 插件 HTTP 客户端
 * 使用主应用暴露的 http 实例（已认证、带 token 刷新），
 * 适配 axios 风格调用（get/post/delete）到 PureHttp API
 */

interface Http {
  get(url: string, config?: any): Promise<any>;
  post(url: string, data?: any, config?: any): Promise<any>;
  delete(url: string, config?: any): Promise<any>;
}

const http: Http = {
  get(url: string, config?: any) {
    return window.__deps.http.get(url, config);
  },
  post(url: string, data?: any, config?: any) {
    return window.__deps.http.post(url, { data, ...config });
  },
  delete(url: string, config?: any) {
    return window.__deps.http.delete(url, config);
  }
};

export { http };
