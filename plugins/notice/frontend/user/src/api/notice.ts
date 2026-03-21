import { http } from "../http";

export function getActive() {
  return http.get("/user/notice/active");
}
