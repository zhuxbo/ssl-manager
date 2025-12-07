<template>
  <el-icon
    color="var(--el-text-color-secondary)"
    style="cursor: pointer"
    @click="copy"
  >
    <CopyDocument />
  </el-icon>
</template>
<script setup lang="ts">
import { message } from "@shared/utils";
import { CopyDocument } from "@element-plus/icons-vue";

const props = defineProps(["copied"]);

const copy = () => {
  navigator.clipboard
    .writeText(props.copied)
    .then(() => {
      message("复制成功", {
        type: "success"
      });
    })
    .catch(() => {
      message("复制失败", {
        type: "error"
      });
    });
};
</script>
