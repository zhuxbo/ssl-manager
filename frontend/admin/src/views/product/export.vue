<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { ElMessage } from "element-plus";
import { exportProduct } from "@/api/product";
import { index as getUserLevels } from "@/api/userLevel";
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
  levelCustom: 0, // 默认基础
  levelCodes: [] as string[]
});

// 重置表单
const resetForm = () => {
  formData.value = {
    brands: [],
    levelCustom: 0,
    levelCodes: []
  };
};

// 会员级别选项
const levelOptions = ref([]);

// 获取会员级别选项
const getLevelOptions = async () => {
  try {
    const response = await getUserLevels({
      custom: formData.value.levelCustom
    });
    if (response.code === 1 && response.data?.items) {
      levelOptions.value = response.data.items.map((item: any) => ({
        label: item.name,
        value: item.code
      }));
    }
  } catch (error) {
    console.error("获取会员级别失败:", error);
  }
};

const loading = ref(false);

// 导出处理
const handleExport = async () => {
  if (loading.value) return;

  loading.value = true;

  try {
    // 准备导出参数
    const exportParams: any = {};

    if (formData.value.brands.length > 0) {
      exportParams.brands = formData.value.brands;
    }

    exportParams.levelCustom = formData.value.levelCustom;

    if (formData.value.levelCodes.length > 0) {
      exportParams.levelCodes = formData.value.levelCodes;
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

// 监听弹窗打开，重置表单并获取级别选项
import { watch } from "vue";
watch(
  () => props.visible,
  newVal => {
    if (newVal) {
      resetForm();
      getLevelOptions();
    }
  }
);

// 监听级别类型变化，重新获取级别选项
watch(
  () => formData.value.levelCustom,
  () => {
    formData.value.levelCodes = []; // 清空已选级别
    getLevelOptions();
  }
);

onMounted(() => {
  getLevelOptions();
});
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

      <el-form-item label="会员级别类型">
        <el-radio-group v-model="formData.levelCustom">
          <el-radio :value="0">基础级别</el-radio>
          <el-radio :value="1">定制级别</el-radio>
        </el-radio-group>
      </el-form-item>

      <el-form-item label="会员级别">
        <el-select
          v-model="formData.levelCodes"
          multiple
          placeholder="选择级别（不选择则导出所有级别）"
          style="width: 100%"
          collapse-tags
          collapse-tags-tooltip
        >
          <el-option
            v-for="option in levelOptions"
            :key="option.value"
            :label="option.label"
            :value="option.value"
          />
        </el-select>
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
