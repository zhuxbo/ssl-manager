interface Http {
  get(url: string, config?: any): Promise<any>;
  post(url: string, data?: any, config?: any): Promise<any>;
  put(url: string, config?: any): Promise<any>;
  delete(url: string, config?: any): Promise<any>;
  request(method: string, url: string, config?: any): Promise<any>;
}

const http: Http = {
  get(url: string, config?: any) {
    return window.__deps.http.get(url, config);
  },
  post(url: string, data?: any, config?: any) {
    return window.__deps.http.post(url, { data, ...config });
  },
  put(url: string, config?: any) {
    return window.__deps.http.request("put", url, config);
  },
  delete(url: string, config?: any) {
    return window.__deps.http.delete(url, config);
  },
  request(method: string, url: string, config?: any) {
    return window.__deps.http.request(method, url, config);
  }
};

export { http };
