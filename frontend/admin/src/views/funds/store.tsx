import { ref } from "vue";
import type { PlusColumn } from "plus-pro-components";
import type { FormRules } from "element-plus";
import {
  show,
  store,
  update,
  FORM_PARAMS_DEFAULT,
  FORM_PARAMS_KEYS,
  type FormParams
} from "@/api/funds";
import { ReRemoteSelect } from "@shared/components/ReRemoteSelect";
import { pickByKeys } from "@/views/system/utils";
import {
  fundPayMethodOptions,
  storeFundStatusOptions,
  fundTypeOptions
} from "./dictionary";
import { index as getUserList } from "@/api/user";

export function useFundsStore(onSearch, search) {
  const showStore = ref(false);
  const storeRef = ref();
  const storeId = ref(0);
  const storeValues = ref<FormParams>({});
  const manualDisabled = ref(false);
  const isDisabled = ref(false);
  const userSelectKey = ref(0); // 用于强制刷新用户选择组件
  const userSelectRef = ref(); // 用户选择组件引用

  const getIsDisabled = () => {
    isDisabled.value =
      (storeId.value > 0 &&
        (storeValues.value.status !== 0 ||
          ["alipay", "wechat"].includes(storeValues.value.pay_method))) ||
      manualDisabled.value;
  };

  const storeColumns: PlusColumn[] = [
    {
      label: "用户名",
      prop: "user_id",
      valueType: "select",
      renderField: (value, onChange) => {
        return (
          <ReRemoteSelect
            key={`user-select-${userSelectKey.value}`}
            ref={userSelectRef}
            modelValue={value}
            uri="/user"
            searchField="quickSearch"
            labelField="username"
            valueField="id"
            itemsField="items"
            totalField="total"
            placeholder="请选择用户"
            onChange={onChange}
            disabled={storeId.value > 0 || manualDisabled.value}
          />
        );
      }
    },
    {
      label: "金额",
      prop: "amount",
      valueType: "input-number",
      fieldProps: {
        placeholder: "请输入金额",
        min: 0,
        precision: 2,
        controlsPosition: "right",
        get disabled() {
          return storeId.value > 0 || manualDisabled.value;
        }
      }
    },
    {
      label: "类型",
      prop: "type",
      valueType: "select",
      fieldProps: {
        placeholder: "请选择类型",
        get disabled() {
          return (
            storeId.value > 0 ||
            storeValues.value.type !== undefined ||
            manualDisabled.value
          );
        }
      },
      options: fundTypeOptions
    },
    {
      label: "支付方式",
      prop: "pay_method",
      valueType: "select",
      fieldProps: {
        placeholder: "请选择支付方式",
        get disabled() {
          return (
            storeId.value > 0 ||
            storeValues.value.type === "deduct" ||
            manualDisabled.value
          );
        }
      },
      options: fundPayMethodOptions
    },
    {
      label: "支付编号",
      prop: "pay_sn",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入支付编号",
        get disabled() {
          return isDisabled.value || storeValues.value.type === "deduct";
        }
      }
    },
    {
      label: "备注",
      prop: "remark",
      valueType: "textarea",
      fieldProps: {
        placeholder: "请输入备注",
        rows: 3
      }
    },
    {
      label: "状态",
      prop: "status",
      valueType: "select",
      fieldProps: {
        placeholder: "请选择状态",
        get disabled() {
          return isDisabled.value;
        }
      },
      options: storeFundStatusOptions
    }
  ];

  const rules: FormRules = {
    user_id: [{ required: true, message: "请选择用户", trigger: "blur" }],
    amount: [
      { required: true, message: "请输入金额", trigger: "blur" },
      { type: "number", message: "金额必须为数字", trigger: "blur" }
    ],
    type: [{ required: true, message: "请选择类型", trigger: "blur" }],
    pay_method: [
      { required: true, message: "请选择支付方式", trigger: "blur" }
    ],
    status: [{ required: true, message: "请选择状态", trigger: "blur" }]
  };

  const openStoreByType = (type: string) => {
    openStoreForm().then(() => {
      storeValues.value.type = type;
      if (type === "deduct") {
        storeValues.value.pay_method = "other";
        storeValues.value.pay_sn = "";
      }
      getIsDisabled();
    });
  };

  // 根据用户名查询用户ID和用户信息
  const findUserByUsername = async (username: string) => {
    const response = await getUserList({
      username: username,
      currentPage: 1,
      pageSize: 1
    });
    if (response?.data?.items?.length > 0) {
      return response.data.items[0];
    }

    return null;
  };

  // 打开表单
  async function openStoreForm(id = 0) {
    showStore.value = true;
    userSelectKey.value = Date.now(); // 强制重新创建组件

    if (id > 0) {
      storeId.value !== id && handleShow(id);
    } else {
      storeRef.value?.formInstance?.resetFields();
      // 过滤空值和0
      storeValues.value = Object.fromEntries(
        Object.entries(FORM_PARAMS_DEFAULT).filter(
          ([_, value]) => value !== 0 && value !== ""
        )
      );
      storeValues.value.status = 0;

      // 如果有username查询参数，自动选择对应用户
      if (search?.value?.username) {
        const user = await findUserByUsername(search.value.username);
        if (user) {
          // 等待组件完成初始化，然后注入用户选项
          setTimeout(() => {
            // 直接注入用户选项到组件
            if (userSelectRef.value) {
              const userOption = {
                label: user.username,
                value: user.id
              };

              // 确保组件有 options 数组
              if (!userSelectRef.value.options) {
                userSelectRef.value.options = [];
              }

              // 检查是否已存在，避免重复
              const exists = userSelectRef.value.options.some(
                option => option.value === user.id
              );

              if (!exists) {
                // 注入选项
                userSelectRef.value.options.push(userOption);
              }

              // 选项注入完成后，设置用户ID
              setTimeout(() => {
                storeValues.value.user_id = user.id;
              }, 100);
            } else {
              // 回退方案
              storeValues.value.user_id = user.id;
            }
          }, 250); // 等待组件初始化
        }
      }
    }
    storeId.value = id;
    manualDisabled.value = false;
    getIsDisabled();
  }

  // 提交表单
  function confirmStoreForm() {
    storeId.value > 0 ? handleUpdate() : handleStore();
  }

  // 关闭表单
  function closeStoreForm() {
    showStore.value = false;
  }

  const handleShow = (id: number) => {
    show(id).then(res => {
      storeValues.value = pickByKeys<FormParams>(res.data, FORM_PARAMS_KEYS);
      // 将金额转换为数字
      storeValues.value.amount = Number(storeValues.value.amount ?? 0);
    });
  };

  const handleStore = () => {
    store(storeValues.value).then(() => {
      onSearch();
      showStore.value = false;
    });
  };

  const handleUpdate = () => {
    update(storeId.value, storeValues.value).then(() => {
      onSearch();
      showStore.value = false;
      // 清空storeId
      storeId.value = 0;
    });
  };

  return {
    showStore,
    storeRef,
    storeId,
    storeValues,
    storeColumns,
    rules,
    openStoreByType,
    openStoreForm,
    confirmStoreForm,
    closeStoreForm,
    userSelectRef
  };
}
