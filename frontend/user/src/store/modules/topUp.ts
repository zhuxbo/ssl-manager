import { defineStore } from "pinia";
import { getProfile } from "@/api/auth";
import { useUserStoreHook } from "@/store/modules/user";

export const topUpDialogStore = defineStore("topUpDialog", {
  state: () => ({
    visible: false
  }),
  actions: {
    showDialog() {
      this.visible = true;
    },
    hideDialog() {
      this.visible = false;
    },
    updateBalance() {
      getProfile().then(res => {
        if (res?.code === 1 && res.data?.balance != null) {
          useUserStoreHook().updateBalance(String(res.data.balance));
        }
      });
    }
  }
});
