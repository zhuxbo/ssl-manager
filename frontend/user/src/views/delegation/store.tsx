import { ref, h } from "vue";
import type { PlusColumn } from "plus-pro-components";
import { store, FORM_PARAMS_DEFAULT, type FormParams } from "@/api/delegation";
import type { FormRules } from "element-plus";
import { message } from "@shared/utils";
import type { CnameGuideOptions } from "@/views/delegation/CnameGuide";

export const useDelegationStore = (
  onSearch: () => void,
  showCnameGuide: (options: CnameGuideOptions) => void
) => {
  const showStore = ref(false);
  const storeRef = ref();
  const storeId = ref(0);
  const storeValues = ref<FormParams>({});

  const storeColumns: PlusColumn[] = [
    {
      label: "委托域",
      prop: "zone",
      valueType: "input",
      fieldProps: {
        placeholder: "例如: example.com"
      },
      renderExtra: () =>
        h("div", {
          style: {
            fontSize: "12px",
            color: "#909399",
            marginTop: "5px"
          }
        })
    },
    {
      label: "委托前缀",
      prop: "prefix",
      valueType: "select",
      options: [
        { label: "_certum", value: "_certum" },
        { label: "_pki-validation", value: "_pki-validation" },
        { label: "_dnsauth", value: "_dnsauth" },
        { label: "_acme-challenge", value: "_acme-challenge" }
      ],
      fieldProps: {
        placeholder: "请选择委托前缀"
      }
    }
  ];

  const rules: FormRules = {
    zone: [
      { required: true, message: "请输入委托域", trigger: "blur" },
      {
        pattern: /^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i,
        message: "请输入正确的域名格式",
        trigger: "blur"
      }
    ],
    prefix: [{ required: true, message: "请选择委托前缀", trigger: "change" }]
  };

  // 打开表单
  function openStoreForm(id = 0) {
    showStore.value = true;

    if (id > 0) {
      // 委托记录不支持编辑
      message("委托记录不支持编辑，请删除后重新创建", {
        type: "warning"
      });
      showStore.value = false;
      return;
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
    handleStore();
  }

  // 关闭表单
  function closeStoreForm() {
    showStore.value = false;
  }

  const handleStore = () => {
    store(storeValues.value).then(res => {
      onSearch();
      showStore.value = false;

      // 显示 CNAME 配置指引
      if (res.data && res.data.cname_to) {
        showCnameGuide(res.data);
      }
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
