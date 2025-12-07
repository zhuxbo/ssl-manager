import { http } from "@/utils/http";

export type NotificationPreferences = Record<string, Record<string, boolean>>;

export function getApiToken() {
  return http.get<
    BaseResponse<{ allowed_ips: string[] }>,
    { allowed_ips: string[] }
  >("/setting/api-token");
}

export function updateApiToken(data: { token: string; allowed_ips: string[] }) {
  return http.put<BaseResponse<null>, { token: string; allowed_ips: string[] }>(
    "/setting/api-token",
    { data }
  );
}

export function getCallback() {
  return http.get<
    BaseResponse<{
      url: string;
      token: string;
      status: number;
    }>,
    { url: string; token: string }
  >("/setting/callback");
}

export function updateCallback(data: {
  url: string;
  token: string;
  status: number;
}) {
  return http.put<
    BaseResponse<null>,
    { url: string; token: string; status: number }
  >("/setting/callback", { data });
}

export function getNotificationPreferences() {
  return http.get<
    BaseResponse<NotificationPreferences>,
    NotificationPreferences
  >("/setting/notification-preferences");
}

export function updateNotificationPreferences(data: NotificationPreferences) {
  return http.put<BaseResponse<null>, NotificationPreferences>(
    "/setting/notification-preferences",
    { data }
  );
}
