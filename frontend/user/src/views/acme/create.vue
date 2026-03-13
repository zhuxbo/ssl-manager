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
      label-width="120px"
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
        </el-col>
      </el-row>

      <el-row :gutter="20">
        <el-col :span="12">
          <el-form-item
            label="标准域名额度"
            prop="purchased_standard_count"
            :rules="rules.purchased_standard_count"
          >
            <el-input-number
              v-model="formData.purchased_standard_count"
              :min="0"
              :max="999"
              style="width: 100%"
            />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item
            label="通配符域名额度"
            prop="purchased_wildcard_count"
            :rules="rules.purchased_wildcard_count"
          >
            <el-input-number
              v-model="formData.purchased_wildcard_count"
              :min="0"
              :max="999"
              style="width: 100%"
            />
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
import type { CreateAcmeForm } from "@/api/acme";
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

const formData = reactive<CreateAcmeForm>({
  product_id: undefined,
  period: "",
  purchased_standard_count: 0,
  purchased_wildcard_count: 0
});

const periodOptions = ref<{ label: string; value: number }[]>([]);

const rules = reactive<FormRules>({
  product_id: [{ required: true, message: "请选择产品", trigger: "change" }],
  period: [{ required: true, message: "请选择有效期", trigger: "change" }],
  purchased_standard_count: [
    { required: true, message: "请输入标准域名额度", trigger: "blur" }
  ],
  purchased_wildcard_count: [
    { required: true, message: "请输入通配符域名额度", trigger: "blur" }
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
      purchased_standard_count: formData.purchased_standard_count,
      purchased_wildcard_count: formData.purchased_wildcard_count
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
