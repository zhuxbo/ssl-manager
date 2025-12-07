<template>
  <div>
    <table class="descriptions">
      <el-text style="margin: 10px 20px">
        <el-button
          type="primary"
          link
          :disabled="cert.status != 'active'"
          @click="install = 'bt'"
          >宝塔面板</el-button
        >
        <el-button
          type="primary"
          link
          :disabled="cert.status != 'active'"
          @click="OrderApi.download(order.id, 'nginx')"
          >Nginx</el-button
        >
        <el-button
          type="primary"
          link
          :disabled="cert.status != 'active'"
          @click="OrderApi.download(order.id, 'apache')"
          >Apache</el-button
        >
        <el-button
          v-if="cert.private_key"
          type="primary"
          link
          :disabled="cert.status != 'active'"
          @click="OrderApi.download(order.id, 'iis')"
        >
          IIS
        </el-button>
        <el-button
          v-if="cert.private_key"
          type="primary"
          link
          :disabled="cert.status != 'active'"
          @click="OrderApi.download(order.id, 'tomcat')"
        >
          Tomcat
        </el-button>
        <el-button
          type="primary"
          link
          :disabled="cert.status != 'active'"
          @click="OrderApi.download(order.id, 'pem')"
          >Pem</el-button
        >
        <el-button
          type="primary"
          link
          :disabled="cert.status != 'active'"
          @click="OrderApi.download(order.id, 'txt')"
          >Txt</el-button
        >
        <el-button
          type="primary"
          link
          :disabled="cert.status != 'active'"
          @click="OrderApi.download(order.id)"
          >全部</el-button
        >
      </el-text>
    </table>
    <el-dialog
      :model-value="install == 'bt'"
      :close-on-click-modal="false"
      :destroy-on-close="true"
      align-center
      style="width: 796px"
      @close="install = ''"
    >
      <template v-if="install == 'bt'" #header>
        <div class="title" style="margin-bottom: 0">宝塔面板</div>
      </template>
      <el-row v-if="install == 'bt'" :gutter="20">
        <el-col :span="12">
          <span>密钥(KEY)</span>
          <span style="float: right"
            ><el-button
              type="primary"
              link
              size="small"
              @click="copy(cert.private_key)"
              >点击复制</el-button
            ></span
          >
          <el-input
            type="textarea"
            :rows="20"
            :model-value="cert.private_key"
            spellcheck="false"
            :input-style="{
              'font-size': '9px',
              'font-family': 'Consolas, Monaco, serif'
            }"
          />
        </el-col>
        <el-col :span="12">
          <span>证书(PEM格式)</span>
          <span style="float: right">
            <el-button
              type="primary"
              link
              size="small"
              @click="copy((cert.cert + '\n' + cert.intermediate_cert).trim())"
              >点击复制</el-button
            >
          </span>
          <el-input
            type="textarea"
            :rows="20"
            :model-value="(cert.cert + '\n' + cert.intermediate_cert).trim()"
            spellcheck="false"
            :input-style="{
              'font-size': '9px',
              'font-family': 'Consolas, Monaco, serif'
            }"
          />
        </el-col>
      </el-row>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { inject, ref, watchEffect } from "vue";
import * as OrderApi from "@/api/order";
import { message } from "@shared/utils";

const order = inject("order") as any;
const cert = inject("cert") as any;

const install = ref("");

const copy = (content: string) => {
  navigator.clipboard
    .writeText(content)
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

watchEffect(() => {
  cert.value.private_key = cert.value.private_key || "";
  cert.value.cert = cert.value.cert || "";
  cert.value.intermediate_cert = cert.value.intermediate_cert || "";
});
</script>

<style scoped lang="scss">
@import url("../../styles/detail.scss");

:deep(.el-textarea__inner) {
  resize: none;
  scrollbar-width: none;
}

:deep(.el-textarea__inner::-webkit-scrollbar) {
  display: none;
}
</style>
