<template>
  <div class="deploy-section">
    <el-tabs v-model="activeTab" class="deploy-tabs">
      <el-tab-pane label="Nginx / Apache" name="nginx">
        <div class="deploy-step">
          <div class="step-title">第一步：安装 sslctl</div>
          <div class="command-block">
            <div class="command-label">Linux</div>
            <div class="command-line">
              <code>{{ commands.install?.linux }}</code>
              <el-button
                type="primary"
                link
                size="small"
                :disabled="!isActive"
                @click="copy(commands.install?.linux)"
                >复制</el-button
              >
            </div>
          </div>
          <div class="command-block">
            <div class="command-label">Windows (PowerShell)</div>
            <div class="command-line">
              <code>{{ commands.install?.windows }}</code>
              <el-button
                type="primary"
                link
                size="small"
                :disabled="!isActive"
                @click="copy(commands.install?.windows)"
                >复制</el-button
              >
            </div>
          </div>
        </div>
        <div class="deploy-step">
          <div class="step-title">第二步：一键部署</div>
          <div class="command-block">
            <div class="command-line">
              <code>{{ commands.deploy }}</code>
              <el-button
                type="primary"
                link
                size="small"
                :disabled="!isActive"
                @click="copy(commands.deploy)"
                >复制</el-button
              >
            </div>
          </div>
        </div>
      </el-tab-pane>
      <el-tab-pane label="IIS" name="iis">
        <div class="deploy-step">
          <div class="step-title">下载自动部署工具，在服务器运行</div>
          <div class="command-line">
            <code>{{ commands.iis_download }}</code>
            <el-button
              type="primary"
              link
              size="small"
              :disabled="!isActive"
              @click="copy(commands.iis_download)"
              >复制</el-button
            >
            <el-link
              v-if="isActive"
              type="primary"
              :href="commands.iis_download"
              target="_blank"
              >下载</el-link
            >
          </div>
        </div>
        <div class="command-block">
          <div class="command-label">部署 URL</div>
          <div class="command-line">
            <code>{{ commands.deploy_url }}</code>
            <el-button
              type="primary"
              link
              size="small"
              :disabled="!isActive"
              @click="copy(commands.deploy_url)"
              >复制</el-button
            >
          </div>
        </div>
        <div class="command-block">
          <div class="command-label">部署 Token</div>
          <div class="command-line">
            <code>{{ commands.token }}</code>
            <el-button
              type="primary"
              link
              size="small"
              :disabled="!isActive"
              @click="copy(commands.token)"
              >复制</el-button
            >
          </div>
        </div>
        <div class="command-block">
          <div class="command-label">订单 ID</div>
          <div class="command-line">
            <code>{{ commands.order_ids }}</code>
            <el-button
              type="primary"
              link
              size="small"
              :disabled="!isActive"
              @click="copy(commands.order_ids)"
              >复制</el-button
            >
          </div>
        </div>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup lang="ts">
import { inject, ref, computed, watch } from "vue";
import * as OrderApi from "@/api/order";
import { message } from "@shared/utils";

const order = inject("order") as any;
const cert = inject("cert") as any;

const commands = ref<any>({});
const activeTab = ref("nginx");

const isActive = computed(() => cert.value?.status === "active");

watch(
  isActive,
  val => {
    if (val) {
      OrderApi.deployCommands(order.id).then(res => {
        if (res.code === 1) {
          commands.value = res.data;
        }
      });
    }
  },
  { immediate: true }
);

const copy = (content: string) => {
  if (!content) return;
  navigator.clipboard
    .writeText(content)
    .then(() => {
      message("复制成功", { type: "success" });
    })
    .catch(() => {
      message("复制失败", { type: "error" });
    });
};
</script>

<style scoped lang="scss">
.deploy-section {
  padding: 5px 0;
}

.deploy-tabs {
  :deep(.el-tabs__header) {
    margin-bottom: 8px;
  }
}

.deploy-step {
  margin-bottom: 12px;
}

.step-title {
  font-weight: 500;
  margin-bottom: 6px;
  color: var(--el-text-color-primary);
}

.command-block {
  margin-bottom: 8px;
}

.command-label {
  font-size: 12px;
  color: var(--el-text-color-secondary);
  margin-bottom: 2px;
}

.command-line {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--el-fill-color-light);
  border-radius: 4px;
  padding: 6px 10px;

  code {
    flex: 1;
    font-family: Consolas, Monaco, monospace;
    font-size: 12px;
    word-break: break-all;
    color: var(--el-text-color-regular);
  }
}
</style>
