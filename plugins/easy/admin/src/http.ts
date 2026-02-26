import axios from "axios";

const http = axios.create({
  baseURL: "/api/admin"
});

http.interceptors.request.use(config => {
  const token = window.__deps?.getAccessToken?.() || "";
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

http.interceptors.response.use(
  response => {
    return response.data;
  },
  error => {
    return Promise.reject(error);
  }
);

export { http };
