<script setup lang="tsx">
import { onMounted, ref } from "vue";
import { PlusDrawerForm } from "plus-pro-components";
import {
  getAllSettings,
  destroyGroup,
  getSettingConfig,
  clearCache
} from "@/api/setting";
import { useSettingGroupStore } from "./groupStore";
import { ElButton, ElPopconfirm } from "element-plus";
import SettingGroup from "./SettingGroup.vue";
import { message } from "@shared/utils";
import { useDrawerSize } from "@/views/system/drawer";

defineOptions({
  name: "Setting"
});

// 使用统一的响应式抽屉宽度
const { drawerSize } = useDrawerSize();

// 加载状态
const loading = ref(false);
// 设置组列表
const groups = ref([]);
// 设置配置
const settingConfig = ref({
  locked: false
});

// 创建设置组表单
const {
  groupFormRef,
  showGroupForm,
  groupId,
  groupValues,
  groupColumns,
  rules,
  openGroupForm,
  confirmGroupForm,
  closeGroupForm
} = useSettingGroupStore(() => loadSettings());

// 获取设置配置
const loadConfig = () => {
  getSettingConfig().then(({ data }) => {
    settingConfig.value = data;
  });
};

// 加载所有设置
const loadSettings = () => {
  loading.value = true;
  getAllSettings().then(({ data }) => {
    groups.value = data.groups || [];
    loading.value = false;
  });
};

// 添加新设置组
const handleAddGroup = () => {
  openGroupForm(0);
};

// 编辑设置组
const handleEditGroup = id => {
  openGroupForm(id);
};

// 删除设置组
const handleDeleteGroup = id => {
  destroyGroup(id).then(() => {
    message("删除成功", { type: "success" });
    loadSettings();
  });
};

// 清除缓存
const handleClearCache = () => {
  clearCache().then(() => {
    message("缓存已清除", { type: "success" });
  });
};

onMounted(() => {
  loadConfig();
  loadSettings();
});
</script>

<template>
  <div class="main">
    <div
      class="setting-header bg-bg_color w-full p-4 mb-4 flex justify-between items-center"
    >
      <h3 class="text-xl font-bold text-gray-600">系统设置</h3>
      <div class="flex gap-2">
        <el-popconfirm
          title="确定要清除所有设置缓存吗？"
          width="240"
          @confirm="handleClearCache"
        >
          <template #reference>
            <el-button type="warning">清除缓存</el-button>
          </template>
        </el-popconfirm>
        <el-button
          v-if="!settingConfig.locked"
          type="primary"
          @click="handleAddGroup"
          >添加设置组</el-button
        >
      </div>
    </div>

    <div v-loading="loading" class="setting-groups">
      <template v-if="groups.length > 0">
        <SettingGroup
          v-for="group in groups"
          :key="group.id"
          :group="group"
          :config="settingConfig"
          :onRefresh="loadSettings"
          @edit-group="handleEditGroup"
          @delete-group="handleDeleteGroup"
        />
      </template>

      <div v-else class="empty-groups text-center py-8 bg-bg_color rounded">
        <p class="text-gray-500 mb-4">暂无设置组</p>
        <el-button
          v-if="!settingConfig.locked"
          type="primary"
          @click="handleAddGroup"
          >添加设置组</el-button
        >
      </div>
    </div>

    <PlusDrawerForm
      ref="groupFormRef"
      v-model="groupValues"
      :visible="showGroupForm"
      :form="{
        columns: groupColumns,
        rules,
        labelPosition: 'right',
        labelSuffix: ''
      }"
      :size="drawerSize"
      :closeOnClickModal="true"
      :title="groupId > 0 ? '编辑设置组' : '新增设置组'"
      confirmText="提交"
      cancelText="取消"
      @confirm="confirmGroupForm"
      @cancel="closeGroupForm"
    />
  </div>
</template>

<style scoped lang="scss">
.main {
  padding: 16px;
}

.setting-header {
  border-radius: 4px;
}
</style>
