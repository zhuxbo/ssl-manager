import { ref, onMounted, computed } from "vue";
import type { PlusColumn } from "plus-pro-components";
import { getCallback, updateCallback } from "@/api/setting";
import type { FormRules } from "element-plus";
import { message } from "@shared/utils";

export const useCallback = () => {
  const callbackValues = ref<{ url: string; token: string; status: number }>({
    url: "",
    token: "",
    status: 0
  });

  const isClose = computed(() => callbackValues.value.status === 0);

  const callbackColumns: PlusColumn[] = [
    {
      label: "回调地址",
      prop: "url",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入回调地址",
        get disabled() {
          return isClose.value;
        }
      }
    },
    {
      label: "回调 Token",
      prop: "token",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入回调 Token",
        get disabled() {
          return isClose.value;
        }
      }
    },
    {
      label: "状态",
      prop: "status",
      valueType: "switch",
      fieldProps: {
        activeValue: 1,
        inactiveValue: 0
      },
      onChange: (value: number) => {
        if (value === 0) {
          callbackValues.value.url = "";
          callbackValues.value.token = "";
          callbackValues.value.status = 0;
        }
      }
    }
  ];

  const callbackRules = computed(
    (): FormRules => ({
      url: [
        {
          required: !isClose.value,
          message: "请输入回调地址",
          trigger: "blur"
        },
        {
          pattern: /^https?:\/\/.+/,
          message: "请输入有效的URL地址（以http://或https://开头）",
          trigger: "blur"
        }
      ],
      token: [
        {
          required: !isClose.value,
          message: "请输入回调Token",
          trigger: "blur"
        },
        { min: 32, max: 128, message: "请输入32-128个字符", trigger: "blur" }
      ],
      status: [{ required: true, message: "请选择状态", trigger: "blur" }]
    })
  );

  onMounted(() => {
    resetCallback();
  });

  const handleCallbackUpdate = () => {
    updateCallback(callbackValues.value).then(() => {
      message("更新成功", {
        type: "success"
      });
    });
  };

  const resetCallback = () => {
    getCallback().then(res => {
      if (res?.data) {
        callbackValues.value.url = res.data.url;
        callbackValues.value.token = res.data.token;
        callbackValues.value.status = res.data.status;
      }
    });
  };

  return {
    callbackValues,
    callbackColumns,
    callbackRules,
    handleCallbackUpdate,
    resetCallback
  };
};
