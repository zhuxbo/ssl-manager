import { defineStore } from "pinia";
import { getProfile } from "@/api/auth";
import { getToken, setToken, type DataInfo } from "@/utils/auth";

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
        const userInfo = getToken();
        if (userInfo) {
          userInfo.balance = res.data.balance;
          setToken(userInfo as unknown as DataInfo<Date>);
        }
      });
    }
  }
});
