<script setup lang="ts">
import { ref, watch, computed, nextTick } from "vue";
import { ElFormItem, ElInput, ElButton } from "element-plus";
import { Plus, Delete } from "@element-plus/icons-vue";

interface OptionItem {
  label: string;
  value: string;
  id: number; // 用于 v-for 的 key
}

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  disabled: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(["update:modelValue"]);

const internalItems = ref<OptionItem[]>([]);
let nextId = 0;

// 初始化时解析
const initData = () => {
  if (Array.isArray(props.modelValue)) {
    internalItems.value = props.modelValue.map((item: any) => ({
      label: item?.label ?? "",
      value: item?.value ?? "",
      id: nextId++
    }));
  }
};

// 监听外部 modelValue 变化
watch(
  () => props.modelValue,
  newValue => {
    if (Array.isArray(newValue)) {
      const currentValue = internalItems.value.map(i => ({
        label: i.label,
        value: i.value
      }));
      if (JSON.stringify(currentValue) !== JSON.stringify(newValue)) {
        internalItems.value = newValue.map((item: any) => ({
          label: item?.label ?? "",
          value: item?.value ?? "",
          id: nextId++
        }));
      }
    }
  },
  { deep: true }
);

// 监听内部变化，发出更新
watch(
  internalItems,
  newItems => {
    // 过滤掉 id 属性再发出
    const itemsToEmit = newItems.map(({ label, value }) => ({ label, value }));
    emit("update:modelValue", itemsToEmit);

    // 如果没有项目，则至少添加一个空项
    if (newItems.length === 0 && !props.disabled) {
      nextTick(() => {
        addItem();
      });
    }
  },
  { deep: true }
);

// 添加新项
const addItem = () => {
  internalItems.value.push({ label: "", value: "", id: nextId++ });
};

// 删除项
const removeItem = (id: number) => {
  internalItems.value = internalItems.value.filter(item => item.id !== id);
};

// 计算属性检查是否有空项（用于按钮禁用等，可选）
const hasEmptyItem = computed(() => {
  return internalItems.value.some(
    item =>
      item.label.trim() === "" ||
      (typeof item.value === "string" && item.value.trim() === "")
  );
});

// 如果没有选项且不是禁用状态，则添加一个
if (internalItems.value.length === 0 && !props.disabled) {
  addItem();
}

// 初始化数据
initData();
</script>

<template>
  <div class="key-value-input">
    <!-- 当没有选项时显示提示 -->
    <div v-if="internalItems.length === 0" class="mt-2 mb-2 text-gray-500">
      暂无选项
    </div>

    <div
      v-for="(item, index) in internalItems"
      :key="item.id"
      class="item-row flex items-center gap-2 mb-2"
    >
      <ElFormItem :prop="`options[${index}].label`" class="flex-1 mb-0">
        <ElInput
          v-model="item.label"
          placeholder="标签 (Label)"
          :disabled="disabled"
        />
      </ElFormItem>
      <ElFormItem :prop="`options[${index}].value`" class="flex-1 mb-0">
        <ElInput
          v-model="item.value"
          placeholder="值 (Value)"
          :disabled="disabled"
        />
      </ElFormItem>
      <ElButton
        type="danger"
        :icon="Delete"
        circle
        plain
        :disabled="disabled"
        @click="removeItem(item.id)"
      />
    </div>
    <ElButton
      type="primary"
      :icon="Plus"
      plain
      :disabled="disabled"
      @click="addItem"
    >
      添加选项
    </ElButton>
  </div>
</template>

<style scoped>
.key-value-input .item-row .el-form-item {
  margin-bottom: 0; /* 移除 FormItem 默认的 margin */
}
</style>
