import { http } from "../http";

export function index(params: Record<string, any>) {
  return http.get("/invoice", { params });
}

export function store(data: Record<string, any>) {
  return http.post("/invoice", data);
}

export function update(id: number, data: Record<string, any>) {
  return http.put(`/invoice/${id}`, { data });
}

export function show(id: number) {
  return http.get(`/invoice/${id}`);
}

export function destroy(id: number) {
  return http.delete(`/invoice/${id}`);
}

export function quota() {
  return http.get("/invoice/quota");
}

export function me() {
  return http.get("/me");
}
