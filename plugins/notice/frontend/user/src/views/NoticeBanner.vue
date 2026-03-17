<script setup lang="ts">
import { ref, onMounted } from "vue";
import { getActive } from "../api/notice";

const STORAGE_KEY = "notice-dismissed-ids";

interface NoticeItem {
  id: number;
  title: string;
  content: string;
  type: string;
}

const notices = ref<NoticeItem[]>([]);

function getDismissedIds(): number[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : [];
  } catch {
    return [];
  }
}

function dismiss(id: number) {
  const ids = getDismissedIds();
  if (!ids.includes(id)) {
    ids.push(id);
    localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
  }
  notices.value = notices.value.filter(n => n.id !== id);
}

onMounted(async () => {
  try {
    const { data } = await getActive();
    const all = data as NoticeItem[];
    const dismissed = getDismissedIds();

    notices.value = all.filter(n => !dismissed.includes(n.id));

    // 清理已不存在的旧 dismissed ID
    const activeIds = new Set(all.map(n => n.id));
    const cleaned = dismissed.filter(id => activeIds.has(id));
    localStorage.setItem(STORAGE_KEY, JSON.stringify(cleaned));
  } catch {
    // 静默失败，不影响 Dashboard
  }
});
</script>

<template>
  <div v-if="notices.length" class="notice-banner-list">
    <el-alert
      v-for="notice in notices"
      :key="notice.id"
      :title="notice.title"
      :description="notice.content"
      :type="(notice.type as any) || 'info'"
      show-icon
      closable
      @close="dismiss(notice.id)"
    />
  </div>
</template>

<style scoped>
.notice-banner-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.notice-banner-list :deep(.el-alert) {
  border-radius: 8px;
  border: none;
  padding: 12px 16px;
}

.notice-banner-list :deep(.el-alert--info) {
  background-color: #fff;
}

.notice-banner-list :deep(.el-alert--success) {
  background-color: #f9fdf7;
}

.notice-banner-list :deep(.el-alert--warning) {
  background-color: #fefcf8;
}

.notice-banner-list :deep(.el-alert--error) {
  background-color: #fef9f9;
}

html.dark .notice-banner-list :deep(.el-alert--info) {
  background-color: #141414;
}

html.dark .notice-banner-list :deep(.el-alert--success) {
  background-color: #141816;
}

html.dark .notice-banner-list :deep(.el-alert--warning) {
  background-color: #181716;
}

html.dark .notice-banner-list :deep(.el-alert--error) {
  background-color: #181516;
}
</style>
