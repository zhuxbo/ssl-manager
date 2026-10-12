<template>
  <section v-if="note" class="review-note" aria-label="审核意见">
    <strong>审核意见</strong>
    <p>{{ note }}</p>
  </section>
</template>

<script setup lang="ts">
defineProps<{ note?: string | null }>();
</script>

<style scoped>
.review-note {
  margin-bottom: 12px;
  color: var(--el-text-color-regular);
}

.review-note p {
  margin: 8px 0 0;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
</style>
