interface Http {
  get(url: string, config?: any): Promise<any>;
}

const http: Http = {
  get(url: string, config?: any) {
    return window.__deps.http.get(url, config);
  }
};

export { http };
