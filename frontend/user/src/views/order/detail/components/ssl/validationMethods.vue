<template>
  <el-select v-model="method" placeholder="请选择验证方法" class="select">
    <template v-for="item in props.methods" :key="item">
      <el-option :label="validationMethodLabels[item]" :value="item" />
    </template>
  </el-select>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { validationMethodLabels } from "@/views/system/dictionary";

const props = defineProps({
  modelValue: {
    type: String
  },
  methods: {
    type: Object
  }
});

const emit = defineEmits(["update:modelValue"]);

const method = computed({
  get() {
    return props.modelValue ?? "";
  },
  set(method) {
    emit("update:modelValue", method);
  }
});
</script>

<style scoped lang="scss">
.select {
  width: 160px;
  margin-right: 0;

  :deep(.el-select__wrapper) {
    border-top-right-radius: 0 !important;
    border-bottom-right-radius: 0 !important;
  }
}
</style>
