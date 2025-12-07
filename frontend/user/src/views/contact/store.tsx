import { ref } from "vue";
import type { PlusColumn } from "plus-pro-components";
import {
  show,
  store,
  update,
  FORM_PARAMS_DEFAULT,
  FORM_PARAMS_KEYS,
  type FormParams
} from "@/api/contact";
import type { FormRules } from "element-plus";
import { pickByKeys } from "@/views/system/utils";

export const useContactStore = (onSearch: () => void) => {
  const showStore = ref(false);
  const storeRef = ref();
  const storeId = ref(0);
  const storeValues = ref<FormParams>({});

  const storeColumns: PlusColumn[] = [
    {
      label: "姓氏",
      prop: "last_name",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入姓氏"
      }
    },
    {
      label: "名字",
      prop: "first_name",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入名字"
      }
    },
    {
      label: "身份证号",
      prop: "identification_number",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入身份证号"
      }
    },
    {
      label: "职位",
      prop: "title",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入职位"
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
      label: "电话",
      prop: "phone",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入电话",
        type: "number"
      }
    }
  ];

  const rules: FormRules = {
    last_name: [
      { required: true, message: "请输入姓氏" },
      { max: 50, message: "姓氏长度不能超过50个字符", trigger: "blur" }
    ],
    first_name: [
      { required: true, message: "请输入名字" },
      { max: 50, message: "名字长度不能超过50个字符", trigger: "blur" }
    ],
    identification_number: [
      {
        pattern:
          /^[1-9]\d{5}(18|19|20)\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{3}[\dXx]$/,
        message: "请输入正确的身份证号",
        trigger: "blur"
      }
    ],
    title: [
      { required: true, message: "请输入职位" },
      { max: 20, message: "职位长度不能超过50个字符", trigger: "blur" }
    ],
    email: [
      { required: true, message: "请输入邮箱" },
      { type: "email", message: "请输入正确的邮箱地址", trigger: "blur" }
    ],
    phone: [
      { required: true, message: "请输入电话" },
      {
        pattern: /^[0-9]{1,15}$/,
        message: "请输入正确的格式",
        trigger: "blur"
      }
    ]
  };

  // 打开表单
  function openStoreForm(id = 0) {
    showStore.value = true;
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
