<template>
  <el-dialog
    :model-value="visible"
    title="导入证书"
    :width="dialogSize"
    @update:model-value="$emit('update:visible', $event)"
  >
    <el-form ref="formRef" :model="form" label-width="100px" class="ml-3 mr-3">
      <el-form-item
        label="用户"
        prop="user_id"
        :rules="[{ required: true, message: '请选择用户', trigger: 'change' }]"
      >
        <re-remote-select
          v-model="form.user_id"
          uri="/user"
          searchField="quickSearch"
          labelField="username"
          valueField="id"
          itemsField="items"
          totalField="total"
          placeholder="请选择用户"
          style="width: 100%"
          @change="handleUserChange"
        />
      </el-form-item>

      <el-form-item
        label="产品"
        prop="product_id"
        :rules="[{ required: true, message: '请选择产品', trigger: 'change' }]"
      >
        <re-remote-select
          v-model="form.product_id"
          uri="/product"
          searchField="quickSearch"
          labelField="name"
          valueField="id"
          itemsField="items"
          totalField="total"
          placeholder="请选择产品"
          style="width: 100%"
          @change="handleProductChange"
        />
      </el-form-item>

      <el-form-item
        label="有效期"
        prop="period"
        :rules="[
          { required: true, message: '请选择有效期', trigger: 'change' }
        ]"
      >
        <el-select
          v-model="form.period"
          placeholder="请选择有效期"
          style="width: 100%"
          :disabled="!form.product_id"
        >
          <el-option
            v-for="period in periodOptions"
            :key="period.value"
            :label="period.label"
            :value="period.value"
          />
        </el-select>
      </el-form-item>

      <el-form-item
        label="接口ID"
        prop="api_id"
        :rules="[{ required: true, message: '请输入接口ID', trigger: 'blur' }]"
      >
        <el-input
          v-model="form.api_id"
          placeholder="请输入CA接口返回的证书ID"
        />
      </el-form-item>

      <el-form-item
        label="通用名"
        prop="common_name"
        :rules="[{ required: true, message: '请输入通用名', trigger: 'blur' }]"
      >
        <el-input
          v-model="form.common_name"
          placeholder="请输入证书的通用名（域名）"
        />
      </el-form-item>

      <el-form-item
        label="操作类型"
        prop="action"
        :rules="[
          { required: true, message: '请选择操作类型', trigger: 'change' }
        ]"
      >
        <el-select
          v-model="form.action"
          placeholder="请选择操作类型"
          style="width: 100%"
        >
          <el-option label="新购" value="new" />
          <el-option label="续费" value="renew" />
          <el-option label="重签" value="reissue" />
        </el-select>
      </el-form-item>

      <el-form-item
        v-if="form.action === 'reissue'"
        label="原订单ID"
        prop="order_id"
        :rules="[
          { required: true, message: '请选择原订单', trigger: 'change' }
        ]"
      >
        <re-remote-select
          :key="form.user_id"
          v-model="form.order_id"
          uri="/order"
          searchField="id"
          labelField="id"
          valueField="id"
          itemsField="items"
          totalField="total"
          placeholder="请选择原订单"
          style="width: 100%"
          :clearable="true"
          :queryParams="{ user_id: form.user_id }"
          :disabled="!form.user_id"
        />
      </el-form-item>

      <el-form-item label="CSR（可选）">
        <el-input
          v-model="form.csr"
          type="textarea"
          :rows="4"
          placeholder="请输入CSR内容（可选）"
        />
      </el-form-item>

      <el-form-item label="私钥（可选）">
        <el-input
          v-model="form.private_key"
          type="textarea"
          :rows="4"
          placeholder="请输入私钥内容（可选）"
        />
      </el-form-item>
    </el-form>

    <template #footer>
      <span class="dialog-footer">
        <el-button @click="handleClose">取消</el-button>
        <el-button type="primary" :loading="loading" @click="handleSubmit"
          >导入</el-button
        >
      </span>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from "vue";
import type { FormInstance } from "element-plus";
import ReRemoteSelect from "@shared/components/ReRemoteSelect";
import { importCert } from "@/api/order";
import { show as getProduct } from "@/api/product";
import { message } from "@shared/utils";
import { periodOptions as allPeriodOptions } from "@/views/system/dictionary";
import { useDialogSize } from "@/views/system/dialog";

interface Props {
  visible: boolean;
}

interface Emits {
  (e: "update:visible", value: boolean): void;
  (e: "success"): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

// 使用统一的响应式对话框宽度
const { dialogSize } = useDialogSize();

const formRef = ref<FormInstance>();
const loading = ref(false);
const selectedProduct = ref<any>(null);

const form = reactive({
  user_id: "",
  product_id: "",
  period: 12,
  api_id: "",
  action: "new",
  order_id: "",
  csr: "",
  private_key: "",
  common_name: ""
});

// 有效期选项
const periodOptions = computed(() => {
  if (!selectedProduct.value?.periods) {
    // 如果没有选择产品，返回常用的周期选项
    return allPeriodOptions.filter(option =>
      [12, 24, 36, 48, 60].includes(option.value)
    );
  }

  // 根据产品支持的周期过滤词典选项
  return allPeriodOptions.filter(option =>
    selectedProduct.value.periods.includes(option.value)
  );
});

// 选择用户时清空订单ID
const handleUserChange = () => {
  form.order_id = "";
};

// 选择产品时获取产品详情并更新有效期选项
const handleProductChange = async (productId: string) => {
  if (!productId) {
    selectedProduct.value = null;
    form.period = 12;
    return;
  }

  const response = await getProduct(Number(productId));
  if (response.code === 1) {
    selectedProduct.value = response.data;

    // 如果当前选择的有效期不在新产品的可选范围内，重置为第一个可选项
    if (
      selectedProduct.value.periods &&
      selectedProduct.value.periods.length > 0
    ) {
      if (!selectedProduct.value.periods.includes(form.period)) {
        form.period = selectedProduct.value.periods[0];
      }
    }
  }
};

// 提交表单
const handleSubmit = async () => {
  if (!formRef.value) return;

  try {
    await formRef.value.validate();
    loading.value = true;

    const data = {
      ...form,
      channel: "admin"
    };

    await importCert(data);
    message("导入成功", { type: "success" });
    handleClose();
    emit("success");
  } finally {
    loading.value = false;
  }
};

// 关闭对话框
const handleClose = () => {
  // 重置表单
  Object.assign(form, {
    user_id: "",
    product_id: "",
    period: 12,
    api_id: "",
    action: "new",
    order_id: "",
    csr: "",
    private_key: "",
    common_name: ""
  });

  selectedProduct.value = null;
  emit("update:visible", false);
};

// 监听visible变化，关闭时重置表单
watch(
  () => props.visible,
  newVal => {
    if (!newVal) {
      formRef.value?.resetFields();
    }
  }
);
</script>
