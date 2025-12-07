import { ref } from "vue";
import type { PlusColumn } from "plus-pro-components";
import { updatePassword, type PasswordParams } from "@/api/auth";
import type { FormRules } from "element-plus";
import { message } from "@shared/utils";
import { useUserStoreHook } from "@/store/modules/user";

export const usePassword = () => {
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
      },
      {
        min: 6,
        max: 32,
        message: "密码长度应在6-32个字符之间",
        trigger: "blur"
      },
      {
        validator: (_rule, value, callback) => {
          if (value && value === passwordValues.value.oldPassword) {
            callback(new Error("新密码不能与旧密码相同"));
          }
          callback();
        }
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

  const resetPassword = () => {
    passwordValues.value.oldPassword = "";
    passwordValues.value.newPassword = "";
  };

  return {
    passwordValues,
    passwordColumns,
    passwordRules,
    handlePasswordUpdate,
    resetPassword
  };
};
