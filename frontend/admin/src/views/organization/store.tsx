import { ref } from "vue";
import type { PlusColumn } from "plus-pro-components";
import type { FormRules } from "element-plus";
import {
  show,
  store,
  update,
  FORM_PARAMS_KEYS,
  FORM_PARAMS_DEFAULT,
  type FormParams
} from "@/api/organization";
import ReRemoteSelect from "@shared/components/ReRemoteSelect";
import { countryCodes } from "@/views/system/country";
import { pickByKeys } from "@/views/system/utils";

export function useOrganizationStore(onSearch) {
  const showStore = ref(false);
  const storeRef = ref();
  const storeId = ref(0);
  const storeValues = ref<FormParams>({});

  const storeColumns: PlusColumn[] = [
    {
      label: "用户",
      prop: "user_id",
      valueType: "select",
      renderField: (value, onChange) => {
        return (
          <ReRemoteSelect
            modelValue={value}
            uri="/user"
            searchField="quickSearch"
            labelField="username"
            valueField="id"
            itemsField="items"
            totalField="total"
            placeholder="请选择用户"
            onChange={onChange}
            refreshKey={storeId.value}
          />
        );
      }
    },
    {
      label: "名称",
      prop: "name",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入组织名称"
      }
    },
    {
      label: "信用代码",
      prop: "registration_number",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入信用代码"
      }
    },
    {
      label: "国家",
      prop: "country",
      valueType: "select",
      fieldProps: {
        placeholder: "请选择国家",
        filterable: true
      },
      options: countryCodes
    },
    {
      label: "省份",
      prop: "state",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入省份"
      }
    },
    {
      label: "城市",
      prop: "city",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入城市"
      }
    },
    {
      label: "地址",
      prop: "address",
      valueType: "textarea",
      fieldProps: {
        placeholder: "请输入地址",
        rows: 3
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
    },
    {
      label: "邮政编码",
      prop: "postcode",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入邮政编码"
      }
    }
  ];

  const rules: FormRules = {
    user_id: [{ required: true, message: "请选择用户", trigger: "blur" }],
    name: [{ required: true, message: "请输入组织名称", trigger: "blur" }],
    registration_number: [
      { required: true, message: "请输入信用代码", trigger: "blur" }
    ],
    country: [{ required: true, message: "请选择国家", trigger: "blur" }],
    state: [{ required: true, message: "请输入省份", trigger: "blur" }],
    city: [{ required: true, message: "请输入城市", trigger: "blur" }],
    address: [{ required: true, message: "请输入地址", trigger: "blur" }],
    phone: [
      { required: true, message: "请输入电话", trigger: "blur" },
      {
        pattern: /^[0-9]{1,15}$/,
        message: "请输入正确的格式",
        trigger: "blur"
      }
    ],
    postcode: [
      { required: true, message: "请输入邮政编码", trigger: "blur" },
      {
        min: 3,
        max: 20,
        message: "邮政编码长度应为3-20个字符",
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
    showStore,
    storeRef,
    storeId,
    storeValues,
    storeColumns,
    rules,
    openStoreForm,
    confirmStoreForm,
    closeStoreForm
  };
}
