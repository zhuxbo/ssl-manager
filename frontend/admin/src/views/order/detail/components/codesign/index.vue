<template>
  <el-tabs v-model="activeTab">
    <el-tab-pane label="订单详情" name="detail">
      <el-row v-if="windowWidth > 1680">
        <el-col :span="12">
          <CodesignProcess />
        </el-col>
        <el-col :span="12">
          <CodesignOrder />
          <CodesignCert />
        </el-col>
      </el-row>
      <el-row v-else>
        <el-col :span="24">
          <CodesignProcess />
          <CodesignOrder />
          <CodesignCert />
        </el-col>
      </el-row>
    </el-tab-pane>
  </el-tabs>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from "vue";
import CodesignProcess from "./process.vue";
import CodesignOrder from "./order.vue";
import CodesignCert from "./cert.vue";

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
