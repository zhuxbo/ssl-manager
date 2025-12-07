<template>
  <div class="re-collapse-container">
    <div class="re-collapse-header" @click="toggleCollapse">
      <div class="re-collapse-left">
        <el-icon class="re-collapse-icon">
          <ArrowDown v-if="isOpen" />
          <ArrowUp v-else />
        </el-icon>
        <span class="re-collapse-title">{{ title }}</span>
      </div>
    </div>
    <transition name="re-collapse">
      <div v-show="isOpen" ref="collapseContent" class="re-collapse-content">
        <slot />
      </div>
    </transition>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from "vue";
import { ArrowUp, ArrowDown } from "@element-plus/icons-vue";

const props = defineProps({
  title: {
    type: String,
    required: true
  },
  modelValue: {
    type: Boolean,
    default: false
  },
  border: {
    type: Boolean,
    default: true
  }
});

const emit = defineEmits(["update:modelValue"]);

const isOpen = ref(props.modelValue);
const collapseContent = ref<HTMLElement | null>(null);

watch(
  () => props.modelValue,
  newValue => {
    isOpen.value = newValue;
  }
);

watch(
  () => isOpen.value,
  newValue => {
    emit("update:modelValue", newValue);
  }
);

const toggleCollapse = () => {
  isOpen.value = !isOpen.value;
};
</script>

<style scoped lang="scss">
.re-collapse-container {
  overflow: hidden;
  border-radius: 4px;
}

.re-collapse-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 0;
  cursor: pointer;
}

.re-collapse-left {
  display: flex;
  align-items: center;
}

.re-collapse-title {
  margin-left: 8px;
  font-size: 14px;
  font-weight: bold;
  color: var(--el-form-label-color, var(--el-text-color-regular));
}

.re-collapse-icon {
  color: var(--el-form-label-color, var(--el-text-color-regular));
  transition: transform 0.1s;
}

.re-collapse-content {
  padding-top: 8px;
  overflow: hidden;
  transition: all 0.1s ease-out;
}
</style>
