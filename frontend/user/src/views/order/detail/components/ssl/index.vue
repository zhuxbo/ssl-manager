<template>
  <el-tabs v-model="activeTab">
    <el-tab-pane label="订单详情" name="detail">
      <el-row v-if="windowWidth > 1680">
        <el-col :span="12">
          <SslProcess />
        </el-col>
        <el-col :span="12">
          <SslOrder />
          <SslCert />
        </el-col>
      </el-row>
      <el-row v-else>
        <el-col :span="24">
          <SslProcess />
          <SslOrder />
          <SslCert />
        </el-col>
      </el-row>
    </el-tab-pane>
    <el-tab-pane label="颁发记录" name="issueList">
      <IssueList :active-tab="activeTab" />
    </el-tab-pane>
  </el-tabs>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from "vue";
import SslProcess from "./process.vue";
import SslOrder from "./order.vue";
import SslCert from "./cert.vue";
import IssueList from "./issueList.vue";

const activeTab = ref("detail");
const windowWidth = ref(window.innerWidth);

const updateWidth = () => {
  windowWidth.value = window.innerWidth;
};

onMounted(() => {
  window.addEventListener("resize", updateWidth);
});

onUnmounted(() => {
  window.removeEventListener("resize", updateWidth);
});
</script>
