import { http } from "../http";

export function getActive() {
  return http.get("/notice/active");
}
