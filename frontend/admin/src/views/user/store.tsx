import { ref, computed } from "vue";
import type { PlusColumn } from "plus-pro-components";
import {
  show,
  store,
  update,
  FORM_PARAMS_KEYS,
  FORM_PARAMS_DEFAULT,
  type FormParams
} from "@/api/user";
import type { FormRules } from "element-plus";
import { pickByKeys } from "@/views/system/utils";
import ReRemoteSelect from "@shared/components/ReRemoteSelect";

export const useUserStore = (onSearch: () => void) => {
  const showStore = ref(false);
  const storeRef = ref();
  const storeId = ref(0);
  const storeValues = ref<FormParams>({
    username: "",
    level_code: "",
    status: 1
  });

  const storeColumns: PlusColumn[] = [
    {
      label: "用户名",
      prop: "username",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入用户名"
      }
    },
    {
      label: "密码",
      prop: "password",
      valueType: "input",
      fieldProps: {
        placeholder: "不修改密码请留空",
        type: "password"
      },
      get rules() {
        const passwordRules = computed(() => [
          {
            required: storeId.value === 0,
            message: "请输入密码",
            trigger: "blur"
          },
          {
            min: 6,
            max: 32,
            message: "密码长度在 6 到 32 个字符",
            trigger: "blur"
          }
        ]);
        return passwordRules.value;
      }
    },
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
        placeholder: "请输入手机号",
        type: "number"
      }
    },
    {
      label: "级别",
      prop: "level_code",
      valueType: "select",
      fieldProps: {
        clearable: true
      },
      renderField: (value, onChange) => {
        return (
          <ReRemoteSelect
            modelValue={value}
            uri="/user-level"
            queryParams={{ custom: 0 }}
            searchField="quickSearch"
            labelField="name"
            valueField="code"
            itemsField="items"
            totalField="total"
            placeholder="请选择级别"
            onChange={onChange}
          />
        );
      }
    },
    {
      label: "定制级别",
      prop: "custom_level_code",
      valueType: "select",
      fieldProps: {
        clearable: true
      },
      renderField: (value, onChange) => {
        return (
          <ReRemoteSelect
            modelValue={value}
            uri="/user-level"
            queryParams={{ custom: 1 }}
            searchField="quickSearch"
            labelField="name"
            valueField="code"
            itemsField="items"
            totalField="total"
            placeholder="请选择定制级别"
            onChange={onChange}
          />
        );
      }
    },
    {
      label: "信用额度",
      prop: "credit_limit",
      valueType: "input-number",
      fieldProps: {
        placeholder: "请输入信用额度",
        precision: 2
      }
    },
    {
      label: "状态",
      prop: "status",
      valueType: "switch",
      fieldProps: {
        activeValue: 1,
        inactiveValue: 0
      }
    }
  ];

  const rules: FormRules = {
    username: [
      { required: true, message: "请输入用户名" },
      {
        min: 2,
        max: 20,
        message: "用户名长度在 3 到 20 个字符",
        trigger: "blur"
      }
    ],
    email: [
      { type: "email", message: "请输入正确的邮箱地址", trigger: "blur" }
    ],
    mobile: [
      {
        pattern: /^1[3-9]\d{9}$/,
        message: "请输入正确的手机号格式",
        trigger: "blur"
      }
    ],
    level_code: [{ required: true, message: "请选择级别" }],
    status: [{ required: true, message: "请选择状态" }]
  };

  // 打开表单
  function openStoreForm(id = 0) {
    showStore.value = true;
    if (id > 0) {
      // 打开不同的id才重新查询
      storeId.value !== id && handleShow(id);
    } else {
      storeRef.value.formInstance?.resetFields();
      storeValues.value = Object.fromEntries(
        Object.entries(FORM_PARAMS_DEFAULT).filter(
          ([_, value]) => value !== 0 && value !== ""
        )
      );
    }
    storeId.value = id;
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
    show(id).then(({ data }) => {
      storeValues.value = pickByKeys<FormParams>(data, FORM_PARAMS_KEYS);
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
    });
  };

  return {
    storeRef,
    showStore,
    storeId,
    storeValues,
    storeColumns,
    rules,
    openStoreForm,
    confirmStoreForm,
    closeStoreForm
  };
};
