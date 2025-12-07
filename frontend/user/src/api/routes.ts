import { http } from "@/utils/http";

export const getAsyncRoutes = () => {
  return http.request<BaseResponse>("get", "/get-async-routes");
};
