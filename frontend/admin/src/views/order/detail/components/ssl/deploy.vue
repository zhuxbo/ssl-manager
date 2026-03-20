<template>
  <div class="deploy-section">
    <el-tabs v-model="activeTab" class="deploy-tabs">
      <el-tab-pane label="宝塔面板" name="bt">
        <div class="deploy-step">
          <div class="step-title">第一步：安装 sslbt</div>
          <div class="command-block">
            <div class="command-label">Linux</div>
            <div class="command-line">
              <code>{{ commands.bt_install?.linux }}</code>
              <el-button
                type="primary"
                link
                size="small"
                :disabled="!isActive"
                @click="copy(commands.bt_install?.linux)"
                >复制</el-button
              >
            </div>
          </div>
        </div>
        <div class="deploy-step">
          <div class="step-title">第二步：一键部署</div>
          <div class="command-block">
            <div class="command-line">
              <code>{{ commands.bt_deploy }}</code>
              <el-button
                type="primary"
                link
                size="small"
                :disabled="!isActive"
                @click="copy(commands.bt_deploy)"
                >复制</el-button
              >
            </div>
          </div>
        </div>
      </el-tab-pane>
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
          <div class="step-title">第一步：安装 sslctlw</div>
          <div class="command-block">
            <div class="command-label">
              <el-link
                v-if="isActive"
                type="primary"
                :href="commands.iis_install?.download"
                target="_blank"
                :underline="false"
                style="font-size: inherit; vertical-align: baseline"
                >下载文件</el-link
              ><template v-else>下载文件</template
              >上传到服务器，或复制链接到服务器下载，在服务器内运行
            </div>
            <div class="command-line">
              <code>{{ commands.iis_install?.download }}</code>
              <el-button
                type="primary"
                link
                size="small"
                :disabled="!isActive"
                @click="copy(commands.iis_install?.download)"
                >复制</el-button
              >
            </div>
          </div>
          <div class="command-block">
            <div class="command-label">Windows (PowerShell)</div>
            <div class="command-line">
              <code>{{ commands.iis_install?.windows }}</code>
              <el-button
                type="primary"
                link
                size="small"
                :disabled="!isActive"
                @click="copy(commands.iis_install?.windows)"
                >复制</el-button
              >
            </div>
          </div>
        </div>
        <div class="deploy-step">
          <div class="step-title">第二步：一键部署</div>
          <div class="command-block">
            <div class="command-line">
              <code>{{ commands.iis_deploy }}</code>
              <el-button
                type="primary"
                link
                size="small"
                :disabled="!isActive"
                @click="copy(commands.iis_deploy)"
                >复制</el-button
              >
            </div>
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
const activeTab = ref("bt");

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
