// 全局响应类型声明

declare global {
  // 基础响应接口
  interface BaseResponse<T = any> {
    code: number;
    data?: T;
    msg?: string;
    errors?: Record<string, string[]>;
  }

  // 成功响应类型
  type SuccessResponse<T = any> = {
    code: 1;
    data: T;
  };

  // 失败响应类型
  type ErrorResponse = {
    code: 0;
    msg: string;
    errors?: Record<string, string[]>;
  };
}

export {};
