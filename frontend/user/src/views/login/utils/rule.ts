import { reactive } from "vue";
import type { FormRules } from "element-plus";

/** 登录校验 */
const loginRules = reactive<FormRules>({
  password: [
    {
      required: true,
      message: "请输入密码",
      trigger: "blur"
    },
    {
      min: 6,
      max: 32,
      message: "密码长度应在6-32个字符之间",
      trigger: "blur"
    }
  ]
});

/** 忘记密码校验 */
const updateRules = reactive<FormRules>({
  email: [
    {
      validator: (_rule, value, callback) => {
        if (value === "") {
          callback(new Error("请输入邮箱"));
        } else if (
          !/^[a-zA-Z0-9_-]+@[a-zA-Z0-9_-]+(\.[a-zA-Z0-9_-]+)+$/.test(value)
        ) {
          callback(new Error("请输入正确的邮箱"));
        } else {
          callback();
        }
      },
      trigger: "blur"
    }
  ],
  verifyCode: [
    {
      required: true,
      message: "请输入验证码",
      trigger: "blur"
    },
    {
      min: 6,
      max: 6,
      message: "验证码长度应为6个数字",
      trigger: "blur"
    }
  ],
  password: [
    {
      required: true,
      message: "请输入密码",
      trigger: "blur"
    },
    {
      min: 6,
      max: 32,
      message: "密码长度应在6-32个字符之间",
      trigger: "blur"
    }
  ]
});

export { loginRules, updateRules };
