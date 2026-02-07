import { ref, onMounted } from "vue";
import * as SettingApi from "@/api/setting";
import type { AutoPreferences } from "@/api/setting";

export function useAutoPreference() {
  const autoSettings = ref<AutoPreferences>({
    auto_renew: false,
    auto_reissue: true
  });
  const autoLoading = ref(false);

  onMounted(async () => {
    try {
      const res = await SettingApi.getAutoPreferences();
      if (res.data) {
        autoSettings.value = res.data;
      }
    } catch (e) {
      console.error("Failed to load auto preferences:", e);
    }
  });

  const handleAutoToggle = async (key: keyof AutoPreferences) => {
    autoLoading.value = true;
    try {
      await SettingApi.updateAutoPreferences({
        [key]: autoSettings.value[key]
      });
    } catch (e) {
      // 恢复原值
      autoSettings.value[key] = !autoSettings.value[key];
      console.error("Failed to update auto preferences:", e);
    } finally {
      autoLoading.value = false;
    }
  };

  return { autoSettings, autoLoading, handleAutoToggle };
}
