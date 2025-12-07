import { ref, onMounted } from "vue";
import type { PlusColumn } from "plus-pro-components";
import { getProfile, updateProfile, type ProfileParams } from "@/api/auth";
import type { FormRules } from "element-plus";
import { pickByKeys } from "@/views/system/utils";
import { message } from "@shared/utils";

export const useAdminProfile = () => {
  const profileValues = ref<ProfileParams>({ email: "", mobile: "" });

  const profileColumns: PlusColumn[] = [
    {
      label: "邮箱",
      prop: "email",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入邮箱"
      }
    },
    {
      label: "手机号",
      prop: "mobile",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入手机号"
      }
    }
  ];

  const profileRules: FormRules = {
    email: [
      { required: true, message: "请输入邮箱", trigger: "blur" },
      { type: "email", message: "请输入正确的邮箱地址", trigger: "blur" }
    ],
    mobile: [
      { required: true, message: "请输入手机号", trigger: "blur" },
      {
        pattern: /^1[3-9]\d{9}$/,
        message: "请输入正确的手机号格式",
        trigger: "blur"
      }
    ]
  };

  onMounted(() => {
    getProfile().then(res => {
      profileValues.value = pickByKeys<ProfileParams>(res.data, [
        "email",
        "mobile"
      ]);
    });
  });

  const handleProfileUpdate = () => {
    updateProfile(profileValues.value).then(() => {
      message("更新成功", {
        type: "success"
      });
    });
  };

  return {
    profileValues,
    profileColumns,
    profileRules,
    handleProfileUpdate
  };
};
