import axios, { type AxiosInstance, type AxiosRequestConfig } from "axios"

export interface HttpClientOptions {
  baseURL: string
  timeout?: number
  tokenKey?: string
  onUnauthorized?: () => void
  onError?: (error: any) => void
}

export function createHttpClient(options: HttpClientOptions): AxiosInstance {
  const { baseURL, timeout = 30000, tokenKey = "token", onUnauthorized, onError } = options

  const instance = axios.create({
    baseURL,
    timeout
  })

  // 请求拦截器
  instance.interceptors.request.use(
    config => {
      const token = localStorage.getItem(tokenKey)
      if (token) {
        config.headers.Authorization = `Bearer ${token}`
      }
      return config
    },
    error => Promise.reject(error)
  )

  // 响应拦截器
  instance.interceptors.response.use(
    response => response.data,
    error => {
      if (error.response?.status === 401) {
        onUnauthorized?.()
      }
      onError?.(error)
      return Promise.reject(error)
    }
  )

  return instance
}
