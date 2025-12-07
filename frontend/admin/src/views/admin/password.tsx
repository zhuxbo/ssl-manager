import { ref } from "vue";
import type { PlusColumn } from "plus-pro-components";
import { updatePassword, type PasswordParams } from "@/api/auth";
import type { FormRules } from "element-plus";
import { message } from "@shared/utils";
import { useUserStoreHook } from "@/store/modules/user";

export const useAdminPassword = () => {
  const passwordValues = ref<PasswordParams>({
    oldPassword: "",
    newPassword: ""
  });

  const passwordColumns: PlusColumn[] = [
    {
      label: "旧密码",
      prop: "oldPassword",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入旧密码"
      }
    },
    {
      label: "新密码",
      prop: "newPassword",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入新密码"
      }
    }
  ];

  const passwordRules: FormRules = {
    oldPassword: [{ required: true, message: "请输入旧密码", trigger: "blur" }],
    newPassword: [
      {
        required: true,
        message: "请输入新密码",
        trigger: "blur"
      }
    ]
  };

  const handlePasswordUpdate = () => {
    updatePassword(passwordValues.value).then(() => {
      message("更新成功", {
        type: "success"
      });
      useUserStoreHook().logOut();
    });
  };

  return {
    passwordValues,
    passwordColumns,
    passwordRules,
    handlePasswordUpdate
  };
};
