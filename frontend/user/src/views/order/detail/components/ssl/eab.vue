<template>
  <el-card shadow="never" :style="{ border: 'none' }" style="margin-top: 16px">
    <h2 class="title">
      <span>EAB 凭据</span>
    </h2>
    <table class="descriptions">
      <tbody>
        <tr>
          <td class="label">Server</td>
          <td class="content">
            <code>{{ order.server_url }}</code>
            <el-button
              type="primary"
              link
              size="small"
              style="margin-left: 8px"
              @click="handleCopy(order.server_url)"
            >
              复制
            </el-button>
          </td>
        </tr>
        <tr>
          <td class="label">EAB Kid</td>
          <td class="content">
            <code>{{ order.eab_kid }}</code>
            <el-button
              type="primary"
              link
              size="small"
              style="margin-left: 8px"
              @click="handleCopy(order.eab_kid)"
            >
              复制
            </el-button>
          </td>
        </tr>
        <tr>
          <td class="label">EAB HMAC Key</td>
          <td class="content">
            <code>{{ order.eab_hmac }}</code>
            <el-button
              type="primary"
              link
              size="small"
              style="margin-left: 8px"
              @click="handleCopy(order.eab_hmac)"
            >
              复制
            </el-button>
          </td>
        </tr>
        <tr>
          <td class="label">使用状态</td>
          <td class="content">
            <el-tag :type="order.eab_used ? 'success' : 'info'" size="small">
              {{ order.eab_used ? "已使用" : "未使用" }}
            </el-tag>
          </td>
        </tr>
      </tbody>
    </table>

    <h2 class="title" style="margin-top: 20px">
      <span>ACME 命令</span>
    </h2>
    <table class="descriptions command-form">
      <tbody>
        <tr>
          <td class="label">工具</td>
          <td class="content">
            <el-select v-model="tool" style="width: 200px">
              <el-option value="certbot" label="Certbot" />
              <el-option value="acmesh" label="acme.sh" />
            </el-select>
          </td>
        </tr>
        <tr>
          <td class="label">验证方式</td>
          <td class="content">
            <el-select v-model="method" style="width: 200px">
              <el-option value="dns-01" label="DNS-01" />
              <el-option value="http-01" label="HTTP-01" />
            </el-select>
          </td>
        </tr>
        <tr>
          <td class="label">域名</td>
          <td class="content">
            <el-input
              v-model="domain"
              type="textarea"
              :rows="3"
              placeholder="每行一个域名"
              spellcheck="false"
            />
          </td>
        </tr>
        <!-- certbot: 单条命令 -->
        <tr v-if="tool === 'certbot'">
          <td class="label">命令</td>
          <td class="content">
            <pre class="command-block">{{ certbotCommand }}</pre>
            <el-button
              type="primary"
              link
              size="small"
              @click="handleCopy(certbotCommand)"
            >
              复制
            </el-button>
          </td>
        </tr>
        <!-- acme.sh: 注册命令 + 签发命令分开复制 -->
        <tr v-if="tool === 'acmesh'">
          <td class="label">注册命令</td>
          <td class="content">
            <pre class="command-block">{{ acmeRegisterCommand }}</pre>
            <el-button
              type="primary"
              link
              size="small"
              @click="handleCopy(acmeRegisterCommand)"
            >
              复制
            </el-button>
          </td>
        </tr>
        <tr v-if="tool === 'acmesh'">
          <td class="label">签发命令</td>
          <td class="content">
            <pre class="command-block">{{ acmeIssueCommand }}</pre>
            <el-button
              type="primary"
              link
              size="small"
              @click="handleCopy(acmeIssueCommand)"
            >
              复制
            </el-button>
          </td>
        </tr>
      </tbody>
    </table>
  </el-card>
</template>

<script setup lang="tsx">
import { inject, ref, computed } from "vue";
import { useClipboard } from "@vueuse/core";
import { message } from "@shared/utils";

const order = inject("order") as any;
const { copy } = useClipboard();

const tool = ref("certbot");
const method = ref("dns-01");
const domain = ref("");

const domainFlags = computed(() => {
  const d = domain.value.trim();
  if (!d) return "-d example.com";
  return d
    .split(/\n/)
    .map(line => line.trim())
    .filter(Boolean)
    .map(v => `-d ${v}`)
    .join(" ");
});

const certbotCommand = computed(() => {
  const challengeMethod =
    method.value === "dns-01" ? "--manual" : "--standalone";
  return `certbot certonly --server ${order.server_url} --eab-kid ${order.eab_kid} --eab-hmac-key ${order.eab_hmac} --preferred-challenges ${method.value} ${challengeMethod} ${domainFlags.value}`;
});

const acmeRegisterCommand = computed(() => {
  return `acme.sh --register-account --server ${order.server_url} --eab-kid ${order.eab_kid} --eab-hmac-key ${order.eab_hmac}`;
});

const acmeIssueCommand = computed(() => {
  const issueMethod =
    method.value === "dns-01"
      ? "--dns --yes-I-know-dns-manual-mode-enough-go-ahead-please"
      : "-w /var/www/html";
  return `acme.sh --issue --server ${order.server_url} ${issueMethod} ${domainFlags.value}`;
});

const handleCopy = (text: string) => {
  if (!text) return;
  copy(text);
  message("已复制到剪贴板", { type: "success" });
};
</script>

<style scoped lang="scss">
@import url("../../styles/detail.scss");

.command-form td {
  padding-top: 6px;
  padding-bottom: 6px;
}

.command-block {
  background: var(--el-fill-color-light);
  padding: 8px 12px;
  border-radius: 4px;
  white-space: pre-wrap;
  word-break: break-all;
  font-size: 12px;
  font-family: Consolas, Monaco, monospace;
  margin: 4px 0;
}
</style>
