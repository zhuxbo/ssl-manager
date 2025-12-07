import { ref, computed, onMounted } from "vue";
import {
  getNotificationPreferences,
  updateNotificationPreferences,
  type NotificationPreferences
} from "@/api/setting";
import { message } from "@shared/utils";

const channelLabels: Record<string, string> = {
  mail: "邮件通知",
  sms: "短信通知"
};

const typeLabels: Record<string, string> = {
  cert_issued: "证书签发通知",
  cert_expire: "证书到期提醒",
  security: "安全提醒"
};

export const useNotificationPreference = () => {
  const notificationValues = ref<NotificationPreferences>({});
  const notificationLoading = ref(false);

  const fetchPreferences = () => {
    notificationLoading.value = true;
    getNotificationPreferences()
      .then(res => {
        notificationValues.value = res.data ?? {};
      })
      .finally(() => {
        notificationLoading.value = false;
      });
  };

  onMounted(() => {
    fetchPreferences();
  });

  const notificationChannels = computed(() => {
    return Object.entries(notificationValues.value).map(([channel, types]) => ({
      key: channel,
      label: channelLabels[channel] ?? channel,
      items: Object.entries(types).map(([type, enabled]) => ({
        type,
        label: typeLabels[type] ?? type,
        value: enabled
      }))
    }));
  });

  const handleToggle = (channel: string, type: string, value: boolean) => {
    if (!notificationValues.value[channel]) return;

    const previous = notificationValues.value[channel][type];

    // 值没有变化，直接返回
    if (previous === value) return;

    // 乐观更新：立即更新本地状态
    notificationValues.value = {
      ...notificationValues.value,
      [channel]: {
        ...notificationValues.value[channel],
        [type]: value
      }
    };

    // 异步更新服务器，不阻塞 UI
    updateNotificationPreferences(notificationValues.value)
      .then(() => {
        message("通知设置已更新", { type: "success" });
      })
      .catch(() => {
        message("更新失败，请重试", { type: "error" });
        // 回滚失败的修改，保持界面与服务器一致
        notificationValues.value = {
          ...notificationValues.value,
          [channel]: {
            ...notificationValues.value[channel],
            [type]: previous
          }
        };
      });
  };

  return {
    notificationValues,
    notificationChannels,
    notificationLoading,
    handleToggle
  };
};
