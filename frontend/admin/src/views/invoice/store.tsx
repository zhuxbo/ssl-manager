import { ref, computed } from "vue";
import type { PlusColumn } from "plus-pro-components";
import type { FormRules } from "element-plus";
import {
  show,
  store,
  update,
  FORM_PARAMS_DEFAULT,
  FORM_PARAMS_KEYS,
  type FormParams
} from "@/api/invoice";
import { ReRemoteSelect } from "@shared/components/ReRemoteSelect";
import { pickByKeys } from "@/views/system/utils";

export function useInvoiceStore(onSearch) {
  const showStore = ref(false);
  const storeRef = ref();
  const storeId = ref(0);
  const storeValues = ref<FormParams>({});
  const isEdit = computed(() => storeId.value > 0);
  const status = ref(0);

  const storeColumns: PlusColumn[] = [
    {
      label: "用户名",
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
            disabled={isEdit.value}
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
          return isEdit.value;
        }
      }
    },
    {
      label: "组织",
      prop: "organization",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入组织名称",
        get disabled() {
          return isEdit.value;
        }
      }
    },
    {
      label: "税号",
      prop: "taxation",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入税号",
        get disabled() {
          return isEdit.value;
        }
      }
    },
    {
      label: "邮箱",
      prop: "email",
      valueType: "input",
      fieldProps: {
        placeholder: "请输入邮箱",
        get disabled() {
          return isEdit.value;
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
      renderField: (value, onChange) => {
        return (
          <el-select
            v-model={value}
            placeholder="请选择状态"
            onChange={onChange}
            disabled={status.value === 2}
          >
            {status.value === 0 && <el-option label="处理中" value={0} />}
            {status.value !== 2 && <el-option label="已开票" value={1} />}
            {status.value > 0 && <el-option label="已作废" value={2} />}
          </el-select>
        );
      }
    }
  ];

  const rules: FormRules = {
    user_id: [{ required: true, message: "请选择用户", trigger: "blur" }],
    amount: [
      { required: true, message: "请输入金额", trigger: "blur" },
      { type: "number", message: "金额必须为数字", trigger: "blur" }
    ],
    organization: [
      { required: true, message: "请输入组织名称", trigger: "blur" }
    ],
    email: [
      { required: true, message: "请输入邮箱", trigger: "blur" },
      { type: "email", message: "请输入正确的邮箱格式", trigger: "blur" }
    ],
    status: [{ required: true, message: "请选择状态", trigger: "blur" }]
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
    storeValues.value.status = storeValues.value.status ?? 0;
    status.value = storeValues.value.status;
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
