import { ref } from "vue";
import type { PlusColumn } from "plus-pro-components";
import {
  show,
  store,
  update,
  FORM_PARAMS_DEFAULT,
  FORM_PARAMS_KEYS,
  type FormParams
} from "@/api/chain";
import type { FormRules } from "element-plus";
import { pickByKeys } from "@/views/system/utils";

export const useChainStore = (onSearch: () => void) => {
  const showStore = ref(false);
  const storeRef = ref();
  const storeId = ref(0);
  const storeValues = ref<FormParams>({});

  const storeColumns: PlusColumn[] = [
    {
      label: "名称",
      prop: "common_name",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入名称"
      }
    },
    {
      label: "中间证书",
      prop: "intermediate_cert",
      valueType: "textarea",
      fieldProps: {
        placeholder: "请输入中间证书",
        rows: 15
      }
    }
  ];

  const rules: FormRules = {
    common_name: [
      { required: true, message: "请输入名称" },
      { max: 200, message: "名称长度不能超过200个字符", trigger: "blur" }
    ],
    intermediate_cert: [{ required: true, message: "请输入中间证书" }]
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
        Object.entries(FORM_PARAMS_DEFAULT).filter(([_, value]) => value !== "")
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
    show(id).then(res => {
      storeValues.value = pickByKeys<FormParams>(res.data, FORM_PARAMS_KEYS);
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
