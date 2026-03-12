<template>
  <el-dialog
    :model-value="visible"
    title="创建订阅"
    :width="dialogSize"
    :before-close="handleClose"
    destroy-on-close
    append-to-body
    @update:model-value="$emit('update:visible', $event)"
  >
    <el-form
      ref="formRef"
      :model="formData"
      :label-position="dialogSize == '90%' ? 'top' : 'right'"
      label-width="90px"
    >
      <el-form-item label="产品" prop="product_id" :rules="rules.product_id">
        <re-remote-select
          v-model="formData.product_id"
          uri="/product"
          searchField="quickSearch"
          labelField="name"
          valueField="id"
          itemsField="items"
          totalField="total"
          placeholder="请选择产品"
          :pageSize="100"
          :showPagination="false"
          :queryParams="{ product_type: 'acme', status: 1 }"
          @change="handleProductChange"
        />
      </el-form-item>

      <el-form-item label="域名" prop="domains" :rules="rules.domains">
        <el-input
          v-model="formData.domains"
          type="textarea"
          :autosize="{ minRows: 3, maxRows: 10 }"
          placeholder="请输入域名，一行一个"
        />
      </el-form-item>

      <el-row :gutter="20">
        <el-col :span="12">
          <el-form-item label="有效期" prop="period" :rules="rules.period">
            <el-select
              v-model="formData.period"
              placeholder="请选择有效期"
              style="width: 100%"
            >
              <el-option
                v-for="option in periodOptions"
                :key="option.value"
                :label="option.label"
                :value="option.value"
              />
            </el-select>
          </el-form-item>
          <el-form-item
            label="验证方式"
            prop="validation_method"
            :rules="rules.validation_method"
          >
            <el-select
              v-model="formData.validation_method"
              placeholder="请选择验证方式"
              style="width: 100%"
            >
              <el-option
                v-for="option in validationMethodOptions"
                :key="option.value"
                :label="option.label"
                :value="option.value"
              />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
    </el-form>
    <template #footer>
      <el-button @click="handleClose">取消</el-button>
      <el-button type="primary" :loading="loading" @click="handleSubmit"
        >提交</el-button
      >
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue";
import { createOrder } from "@/api/acme";
import type { CreateAcmeOrderForm } from "@/api/acme";
import { show as productShow } from "@/api/product";
import { message } from "@shared/utils";
import ReRemoteSelect from "@shared/components/ReRemoteSelect";
import {
  periodLabels,
  validationMethodLabels
} from "@/views/system/dictionary";
import type { FormInstance, FormRules } from "element-plus";
import { useDialogSize } from "@/views/system/dialog";

defineProps({
  visible: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(["update:visible", "success"]);

const { dialogSize } = useDialogSize();
const formRef = ref<FormInstance>();
const loading = ref(false);

const formData = reactive<CreateAcmeOrderForm>({
  product_id: undefined,
  domains: "",
  period: "",
  validation_method: ""
});

const periodOptions = ref<{ label: string; value: number }[]>([]);
const validationMethodOptions = ref<{ label: string; value: string }[]>([]);

const rules = reactive<FormRules>({
  product_id: [{ required: true, message: "请选择产品", trigger: "change" }],
  domains: [{ required: true, message: "请输入域名", trigger: "blur" }],
  period: [{ required: true, message: "请选择有效期", trigger: "change" }],
  validation_method: [
    { required: true, message: "请选择验证方式", trigger: "change" }
  ]
});

const handleProductChange = (productId: number) => {
  if (!productId) return;

  productShow(productId).then(({ data }) => {
    formData.period = "";
    periodOptions.value = [];
    if (data.periods?.length > 0) {
      const sorted = [...data.periods].sort((a: number, b: number) => a - b);
      periodOptions.value = sorted.map(p => ({
        label: periodLabels[p],
        value: p
      }));
      formData.period = sorted[0];
    }

    formData.validation_method = "";
    validationMethodOptions.value = [];
    if (data.validation_methods?.length > 0) {
      const sorted = [...data.validation_methods].sort(
        (a: string, b: string) =>
          Object.keys(validationMethodLabels).indexOf(a) -
          Object.keys(validationMethodLabels).indexOf(b)
      );
      validationMethodOptions.value = sorted.map(m => ({
        label: validationMethodLabels[m],
        value: m
      }));
      formData.validation_method = sorted[0];
    }
  });
};

const handleSubmit = async () => {
  if (!formRef.value) return;
  await formRef.value.validate();

  loading.value = true;
  try {
    const res = await createOrder({
      product_id: formData.product_id,
      period: Number(formData.period),
      domains: formData.domains.replace(/\n/g, ","),
      validation_method: formData.validation_method
    });
    if (res.code === 1) {
      message("创建成功", { type: "success" });
      emit("success");
      emit("update:visible", false);
    }
  } finally {
    loading.value = false;
  }
};

const handleClose = () => {
  emit("update:visible", false);
};
</script>
