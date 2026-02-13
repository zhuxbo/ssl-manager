<template>
  <div class="main">
    <template v-for="(_item, index) in details" :key="index">
      <div class="layout-main bg-bg_color">
        <Detail v-model="details[index]" />
      </div>
    </template>
  </div>
</template>
<script setup lang="ts">
import { onMounted, ref } from "vue";
import { batchShow } from "@/api/order";
import Detail from "./detail/index.vue";
import router from "@/router";

defineOptions({
  name: "OrderDetails"
});

const details = ref<any[]>([]);
const getDetails = () => {
  const ids = router.currentRoute.value.params.ids;
  if (ids) {
    batchShow(ids.toString()).then(res => {
      details.value = res.data.items;
    });
  }
};

onMounted(() => {
  getDetails();
});
</script>
<style scoped lang="scss">
.layout-main {
  width: 100%;
  height: 100%;
  margin-bottom: 20px;
  overflow: hidden;
}
</style>
