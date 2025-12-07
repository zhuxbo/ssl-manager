<script setup lang="tsx">
import { ref } from "vue";
import PureDescriptions from "@pureadmin/descriptions";
import "vue-json-pretty/lib/styles.css";
import VueJsonPretty from "vue-json-pretty";
import dayjs from "dayjs";

const props = defineProps({
  data: {
    type: Object,
    default: () => ({})
  }
});

const columns = [
  {
    label: "ID",
    prop: "id"
  },
  {
    label: "订单ID",
    prop: "order_id"
  },
  {
    label: "动作",
    prop: "action"
  },
  {
    label: "状态",
    prop: "status"
  },
  {
    label: "执行次数",
    prop: "attempts"
  },
  {
    label: "来源",
    prop: "source"
  },
  {
    label: "开始时间",
    prop: "started_at",
    cellRenderer: () => {
      return props.data.started_at
        ? dayjs(props.data.started_at).format("YYYY-MM-DD HH:mm:ss")
        : "-";
    }
  },
  {
    label: "最后执行时间",
    prop: "last_execute_at",
    cellRenderer: () => {
      return props.data.last_execute_at
        ? dayjs(props.data.last_execute_at).format("YYYY-MM-DD HH:mm:ss")
        : "-";
    }
  },
  {
    label: "创建时间",
    prop: "created_at",
    cellRenderer: () => {
      return props.data.created_at
        ? dayjs(props.data.created_at).format("YYYY-MM-DD HH:mm:ss")
        : "-";
    }
  }
];

const dataList = ref([
  {
    title: "响应",
    name: "result",
    data: props.data.result
  }
]);
</script>

<template>
  <div>
    <el-scrollbar>
      <PureDescriptions border :data="[data]" :columns="columns" :column="5" />
    </el-scrollbar>
    <el-tabs :modelValue="dataList[0].name" type="border-card" class="mt-4">
      <el-tab-pane
        v-for="(item, index) in dataList"
        :key="index"
        :name="item.name"
        :label="item.title"
      >
        <el-scrollbar max-height="calc(100vh - 240px)">
          <vue-json-pretty v-model:data="item.data" />
        </el-scrollbar>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>
