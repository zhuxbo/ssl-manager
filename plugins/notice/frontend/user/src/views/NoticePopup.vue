<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { getActive } from "../api/notice";

const STORAGE_KEY = "notice-dismissed-popup";

interface NoticeItem {
  id: number;
  title: string;
  content: string;
  type: string;
}

const visible = ref(false);
const current = ref(0);
const notices = ref<NoticeItem[]>([]);

const currentNotice = computed(() => notices.value[current.value]);

const typeColorMap: Record<string, string> = {
  info: "var(--el-color-info)",
  warning: "var(--el-color-warning)",
  success: "var(--el-color-success)",
  error: "var(--el-color-danger)"
};

function getDismissedIds(): number[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : [];
  } catch {
    return [];
  }
}

function dismiss() {
  const ids = getDismissedIds();
  const n = currentNotice.value;
  if (n && !ids.includes(n.id)) {
    ids.push(n.id);
    localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
  }
  notices.value.splice(current.value, 1);
  if (notices.value.length === 0) {
    visible.value = false;
  } else if (current.value >= notices.value.length) {
    current.value = notices.value.length - 1;
  }
}

onMounted(async () => {
  try {
    const { data } = await getActive("popup");
    const all = data as NoticeItem[];
    const dismissed = getDismissedIds();

    notices.value = all.filter(n => !dismissed.includes(n.id));

    if (notices.value.length > 0) {
      visible.value = true;
    }

    const activeIds = new Set(all.map(n => n.id));
    const cleaned = dismissed.filter(id => activeIds.has(id));
    localStorage.setItem(STORAGE_KEY, JSON.stringify(cleaned));
  } catch {
    // 静默失败
  }
});
</script>

<template>
  <el-dialog
    v-if="currentNotice"
    v-model="visible"
    :title="currentNotice.title"
    width="520px"
    :close-on-click-modal="false"
    destroy-on-close
  >
    <p class="notice-content">{{ currentNotice.content }}</p>
    <div v-if="notices.length > 1" class="notice-nav">
      <el-button
        text
        :disabled="current <= 0"
        @click="current--"
      >
        上一条
      </el-button>
      <span class="notice-indicator">{{ current + 1 }} / {{ notices.length }}</span>
      <el-button
        text
        :disabled="current >= notices.length - 1"
        @click="current++"
      >
        下一条
      </el-button>
    </div>
    <template #footer>
      <el-button
        :style="{
          backgroundColor: typeColorMap[currentNotice.type] || typeColorMap.info,
          borderColor: typeColorMap[currentNotice.type] || typeColorMap.info,
          color: '#fff'
        }"
        @click="dismiss"
      >
        不再显示
      </el-button>
    </template>
  </el-dialog>
</template>

<style scoped>
.notice-content {
  margin: 0;
  font-size: 14px;
  line-height: 1.8;
  color: var(--el-text-color-regular);
  white-space: pre-wrap;
  text-indent: 2em;
}

.notice-nav {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 12px;
}

.notice-indicator {
  font-size: 13px;
  color: var(--el-text-color-secondary);
}
</style>
