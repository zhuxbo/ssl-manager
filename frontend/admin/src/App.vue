<template>
  <el-config-provider :locale="currentLocale">
    <router-view />
    <ReDialog />
    <GlobalUserInfoCard />
  </el-config-provider>
</template>

<script lang="ts">
import { defineComponent } from "vue";
import { ElConfigProvider } from "element-plus";
import { ReDialog } from "@shared/components/ReDialog";
import GlobalUserInfoCard from "@/components/GlobalUserInfoCard/index.vue";
import zhCn from "element-plus/es/locale/lang/zh-cn";

export default defineComponent({
  name: "app",
  components: {
    [ElConfigProvider.name]: ElConfigProvider,
    ReDialog,
    GlobalUserInfoCard
  },
  computed: {
    currentLocale() {
      return zhCn;
    }
  }
});
</script>
