import { http } from "../http";

export function getActive(position?: string) {
  return http.get("/notice/active", { params: position ? { position } : {} });
}
