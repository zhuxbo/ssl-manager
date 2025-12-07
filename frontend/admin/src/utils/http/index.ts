/**
 * Http 模块包装器
 * 提供与原 API 兼容的接口，内部使用 shared 的实现
 */
export { http } from "@shared/utils";
export type {
  RequestMethods,
  PureHttpError,
  PureHttpResponse,
  PureHttpRequestConfig
} from "@shared/utils";
