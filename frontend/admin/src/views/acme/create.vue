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
      label-width="110px"
    >
      <el-form-item label="用户" prop="user_id" :rules="rules.user_id">
        <re-remote-select
          v-model="formData.user_id"
          uri="/user"
          searchField="quickSearch"
          labelField="username"
          valueField="id"
          itemsField="items"
          totalField="total"
          placeholder="请选择用户"
          :queryParams="{ status: 1 }"
        />
      </el-form-item>

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

      <template v-if="showSanFields">
        <el-form-item label="标准域名数量" prop="purchased_standard_count">
          <el-input-number
            v-model="formData.purchased_standard_count"
            :min="standardMin"
            :max="standardMax"
            style="width: 100%"
          />
        </el-form-item>

        <el-form-item label="通配符域名数量" prop="purchased_wildcard_count">
          <el-input-number
            v-model="formData.purchased_wildcard_count"
            :min="wildcardMin"
            :max="wildcardMax"
            style="width: 100%"
          />
        </el-form-item>
      </template>
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
import { show as productShow } from "@/api/product";
import { message } from "@shared/utils";
import ReRemoteSelect from "@shared/components/ReRemoteSelect";
import { periodLabels } from "@/views/system/dictionary";
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

const formData = reactive({
  user_id: undefined as number | undefined,
  product_id: undefined as number | undefined,
  period: "" as number | string,
  purchased_standard_count: 1,
  purchased_wildcard_count: 0
});

const periodOptions = ref<{ label: string; value: number }[]>([]);
const showSanFields = ref(false);
const standardMin = ref(0);
const standardMax = ref(0);
const wildcardMin = ref(0);
const wildcardMax = ref(0);

const rules = reactive<FormRules>({
  user_id: [{ required: true, message: "请选择用户", trigger: "change" }],
  product_id: [{ required: true, message: "请选择产品", trigger: "change" }],
  period: [{ required: true, message: "请选择有效期", trigger: "change" }]
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

    // 判断是否支持多域名（standard_max > 1 或 wildcard_max > 0）
    const sMax = data.standard_max || 1;
    const wMax = data.wildcard_max || 0;
    showSanFields.value = sMax > 1 || wMax > 0;
    standardMin.value = data.standard_min || 0;
    standardMax.value = sMax;
    wildcardMin.value = data.wildcard_min || 0;
    wildcardMax.value = wMax;

    formData.purchased_standard_count = data.standard_min || 1;
    formData.purchased_wildcard_count = data.wildcard_min || 0;
  });
};

const handleSubmit = async () => {
  if (!formRef.value) return;
  await formRef.value.validate();

  loading.value = true;
  try {
    const submitData: any = {
      user_id: formData.user_id,
      product_id: formData.product_id,
      period: Number(formData.period)
    };

    if (showSanFields.value) {
      submitData.purchased_standard_count = formData.purchased_standard_count;
      submitData.purchased_wildcard_count = formData.purchased_wildcard_count;
    }

    const res = await createOrder(submitData);
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
