<script setup lang="ts">
import { ref, computed } from "vue";
import { ElMessage } from "element-plus";
import { exportProduct } from "@/api/product";
import { brandOptions } from "../system/dictionary";

interface Props {
  visible?: boolean;
}

interface Emits {
  (e: "update:visible", value: boolean): void;
}

const props = withDefaults(defineProps<Props>(), {
  visible: false
});

const emit = defineEmits<Emits>();

const dialogVisible = computed({
  get: () => props.visible,
  set: value => emit("update:visible", value)
});

// 表单数据
const formData = ref({
  brands: [] as string[],
  priceRate: 1
});

// 重置表单
const resetForm = () => {
  formData.value = {
    brands: [],
    priceRate: 1
  };
};

const loading = ref(false);

// 导出处理
const handleExport = async () => {
  if (loading.value) return;

  // 验证价格倍率
  if (formData.value.priceRate <= 0) {
    ElMessage.error("价格倍率必须大于0");
    return;
  }

  loading.value = true;

  try {
    // 准备导出参数
    const exportParams: any = {};

    if (formData.value.brands.length > 0) {
      exportParams.brands = formData.value.brands;
    }

    if (formData.value.priceRate !== 1) {
      exportParams.priceRate = formData.value.priceRate;
    }

    // 调用导出API，API本身会处理下载
    await exportProduct(exportParams);

    ElMessage.success("导出成功");
    dialogVisible.value = false;
  } catch (error: any) {
    console.error("导出失败:", error);
    ElMessage.error(error.message || "导出失败");
  } finally {
    loading.value = false;
  }
};

// 取消处理
const handleCancel = () => {
  dialogVisible.value = false;
  resetForm();
};

// 监听弹窗打开，重置表单
import { watch } from "vue";
watch(
  () => props.visible,
  newVal => {
    if (newVal) {
      resetForm();
    }
  }
);
</script>

<template>
  <el-dialog
    v-model="dialogVisible"
    title="导出产品价格"
    width="500px"
    destroy-on-close
    @close="handleCancel"
  >
    <el-form :model="formData" label-width="120px" label-position="right">
      <el-form-item label="品牌筛选">
        <el-select
          v-model="formData.brands"
          multiple
          placeholder="选择品牌（不选择则导出所有品牌）"
          style="width: 100%"
          collapse-tags
          collapse-tags-tooltip
        >
          <el-option
            v-for="option in brandOptions"
            :key="option.value"
            :label="option.label"
            :value="option.value"
          />
        </el-select>
      </el-form-item>

      <el-form-item label="价格倍率">
        <el-input-number
          v-model="formData.priceRate"
          :min="0.01"
          :max="1000"
          :step="0.1"
          :precision="2"
          style="width: 100%"
          placeholder="输入价格倍率"
        />
      </el-form-item>
    </el-form>

    <template #footer>
      <div class="dialog-footer">
        <el-button @click="handleCancel">取消</el-button>
        <el-button type="primary" :loading="loading" @click="handleExport">
          {{ loading ? "导出中..." : "导出" }}
        </el-button>
      </div>
    </template>
  </el-dialog>
</template>
