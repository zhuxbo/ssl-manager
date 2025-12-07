import { h, onMounted, ref, defineComponent } from "vue";
import type { PlusColumn } from "plus-pro-components";
import { getProfile, updateUsername, bindEmail, bindMobile } from "@/api/auth";
import { sendEmailCode, sendSmsCode } from "@/api/verifyCode";
import type { FormRules } from "element-plus";
import { message } from "@shared/utils";
import { ElButton, ElDialog, ElForm, ElFormItem, ElInput } from "element-plus";

// 邮箱正则
const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
// 手机号正则
const mobileRegex = /^1[3-9]\d{9}$/;

export const useProfile = () => {
  const profileValues = ref<{
    username: string;
    email: string;
    mobile: string;
    token: string;
  }>({
    username: "",
    email: "",
    mobile: "",
    token: ""
  });

  const verifyDialogVisible = ref(false);
  const verifyType = ref<"email" | "mobile">("email");
  const verifyCode = ref("");
  const countdown = ref(0);
  let timer: number | null = null;

  const startCountdown = () => {
    countdown.value = 60;
    timer = window.setInterval(() => {
      countdown.value--;
      if (countdown.value <= 0 && timer) {
        clearInterval(timer);
        timer = null;
      }
    }, 1000);
  };

  const handleSendCode = () => {
    if (verifyType.value === "email") {
      sendEmailCode({
        email: profileValues.value.email,
        type: "bind"
      })
        .then(() => {
          startCountdown();
          message("验证码已发送", { type: "success" });
        })
        .catch(() => {
          message("发送验证码失败", { type: "error" });
        });
    } else {
      sendSmsCode({
        mobile: profileValues.value.mobile,
        type: "bind"
      })
        .then(() => {
          startCountdown();
          message("验证码已发送", { type: "success" });
        })
        .catch(() => {
          message("发送验证码失败", { type: "error" });
        });
    }
  };

  const handleVerifySubmit = () => {
    if (verifyType.value === "email") {
      bindEmail({
        email: profileValues.value.email,
        code: verifyCode.value
      })
        .then(() => {
          verifyDialogVisible.value = false;
          message("绑定成功", { type: "success" });
        })
        .catch(() => {
          message("绑定失败", { type: "error" });
        });
    } else {
      bindMobile({
        mobile: profileValues.value.mobile,
        code: verifyCode.value
      })
        .then(() => {
          verifyDialogVisible.value = false;
          message("绑定成功", { type: "success" });
        })
        .catch(() => {
          message("绑定失败", { type: "error" });
        });
    }
  };

  const showVerifyDialog = (type: "email" | "mobile") => {
    verifyType.value = type;
    verifyDialogVisible.value = true;
    verifyCode.value = "";
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
    countdown.value = 0;
  };

  const profileColumns: PlusColumn[] = [
    {
      label: "用户名",
      prop: "username",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入用户名"
      },
      fieldSlots: {
        append: () =>
          h(
            ElButton,
            {
              type: "primary",
              onClick: () => {
                handleUsernameUpdate();
              }
            },
            () => "保存"
          )
      }
    },
    {
      label: "邮箱",
      prop: "email",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入邮箱"
      },
      fieldSlots: {
        append: () =>
          h(
            ElButton,
            {
              type: "primary",
              onClick: () => {
                handleEmailUpdate();
              }
            },
            () => "保存"
          )
      }
    },
    {
      label: "手机号",
      prop: "mobile",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入手机号"
      },
      fieldSlots: {
        append: () =>
          h(
            ElButton,
            {
              type: "primary",
              onClick: () => {
                handleMobileUpdate();
              }
            },
            () => "保存"
          )
      }
    }
  ];

  onMounted(() => {
    getProfile().then(res => {
      profileValues.value.username = res.data.username;
      profileValues.value.email = res.data.email;
      profileValues.value.mobile = res.data.mobile;
    });
  });

  const profileRules: FormRules = {
    username: [
      { required: true, message: "请输入用户名", trigger: "blur" },
      { min: 3, max: 16, message: "请输入3-16个字符", trigger: "blur" }
    ],
    email: [
      { required: true, message: "请输入邮箱", trigger: "blur" },
      {
        validator: (rule, value, callback) => {
          if (!emailRegex.test(value)) {
            callback(new Error("请输入正确的邮箱格式"));
          } else {
            callback();
          }
        },
        trigger: "blur"
      }
    ],
    mobile: [
      { required: true, message: "请输入手机号", trigger: "blur" },
      {
        validator: (rule, value, callback) => {
          if (!mobileRegex.test(value)) {
            callback(new Error("请输入正确的手机号格式"));
          } else {
            callback();
          }
        },
        trigger: "blur"
      }
    ]
  };

  const handleUsernameUpdate = () => {
    updateUsername({ username: profileValues.value.username }).then(() => {
      message("更新成功", {
        type: "success"
      });
    });
  };

  const handleEmailUpdate = () => {
    if (!emailRegex.test(profileValues.value.email)) {
      message("请输入正确的邮箱格式", { type: "error" });
      return;
    }
    showVerifyDialog("email");
  };

  const handleMobileUpdate = () => {
    if (!mobileRegex.test(profileValues.value.mobile)) {
      message("请输入正确的手机号格式", { type: "error" });
      return;
    }
    showVerifyDialog("mobile");
  };

  return {
    profileValues,
    profileColumns,
    profileRules,
    verifyDialogVisible,
    verifyType,
    verifyCode,
    countdown,
    handleSendCode,
    handleVerifySubmit
  };
};

export const VerifyDialog = defineComponent({
  props: {
    visible: Boolean,
    type: {
      type: String as PropType<"email" | "mobile">,
      required: true
    },
    countdown: Number,
    verifyCode: String,
    onSendCode: Function as PropType<() => void>,
    onSubmit: Function as PropType<() => void>,
    onClose: Function as PropType<() => void>,
    onUpdateVerifyCode: Function as PropType<(code: string) => void>
  },
  setup(props) {
    return () => (
      <ElDialog
        modelValue={props.visible}
        title={props.type === "email" ? "绑定邮箱" : "绑定手机号"}
        width="320px"
        onClose={props.onClose}
      >
        <ElForm>
          <ElFormItem>
            <div class="flex items-center">
              <ElInput
                modelValue={props.verifyCode}
                onUpdate:modelValue={value => props.onUpdateVerifyCode?.(value)}
                placeholder="请输入验证码"
                class="mr-3"
              />
              <ElButton
                type="primary"
                disabled={props.countdown > 0}
                onClick={props.onSendCode}
              >
                {props.countdown > 0
                  ? `${props.countdown}s后重试`
                  : "获取验证码"}
              </ElButton>
            </div>
          </ElFormItem>
          <ElFormItem>
            <ElButton type="primary" onClick={props.onSubmit}>
              确认绑定
            </ElButton>
          </ElFormItem>
        </ElForm>
      </ElDialog>
    );
  }
});
