<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { ElDrawer } from "element-plus";
import { show } from "@/api/cert";
import dayjs from "dayjs";
import { status, channel, action } from "@/views/order/dictionary";
import { useDrawerSize } from "@/views/system/drawer";

const props = defineProps({
  id: {
    type: Number,
    default: 0
  },
  modelValue: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(["update:modelValue"]);

// 使用统一的响应式抽屉宽度
const { drawerSize } = useDrawerSize();

const drawerVisible = computed({
  get: () => props.modelValue,
  set: val => emit("update:modelValue", val)
});

const detail = ref();

const loadDetail = () => {
  if (props.id) {
    show(props.id).then(res => {
      detail.value = res.data;
    });
  }
};

watch(
  () => drawerVisible.value,
  val => {
    if (val) {
      loadDetail();
    }
  }
);

watch(
  () => props.id,
  () => {
    if (drawerVisible.value) {
      loadDetail();
    }
  }
);
</script>

<template>
  <el-drawer v-model="drawerVisible" title="证书详情" :size="drawerSize">
    <el-descriptions :column="1" border label-width="100" class="mt-4">
      <el-descriptions-item label-align="right" label="ID">
        {{ detail?.id }}
      </el-descriptions-item>
      <el-descriptions-item label-align="right" label="订单ID">
        {{ detail?.order_id }}
      </el-descriptions-item>
      <el-descriptions-item label-align="right" label="接口ID">
        {{ detail?.api_id }}
      </el-descriptions-item>
      <el-descriptions-item label-align="right" label="操作">
        {{ action[detail?.action] }}
      </el-descriptions-item>
      <el-descriptions-item label-align="right" label="渠道">
        {{ channel[detail?.channel] }}
      </el-descriptions-item>
      <el-descriptions-item label-align="right" label="通用名称">
        {{ detail?.common_name }}
      </el-descriptions-item>
      <el-descriptions-item label-align="right" label="备用名称">
        <ul v-if="detail?.alternative_names">
          <li v-for="name in detail?.alternative_names.split(',')" :key="name">
            {{ name }}
          </li>
        </ul>
      </el-descriptions-item>
      <el-descriptions-item label-align="right" label="域名数量">
        {{
          detail?.standard_count ? detail?.standard_count + "个标准域名" : ""
        }}
        {{ detail?.standard_count && detail?.wildcard_count ? "/" : "" }}
        {{ detail?.wildcard_count ? detail?.wildcard_count + "个通配符" : "" }}
      </el-descriptions-item>
      <el-descriptions-item label-align="right" label="金额">
        {{ detail?.amount }}
      </el-descriptions-item>
      <el-descriptions-item label-align="right" label="状态">
        {{ status[detail?.status] }}
      </el-descriptions-item>
      <el-descriptions-item label-align="right" label="签发时间">
        {{
          detail?.issued_at
            ? dayjs(detail?.issued_at).format("YYYY-MM-DD HH:mm:ss")
            : "-"
        }}
      </el-descriptions-item>
      <el-descriptions-item label-align="right" label="过期时间">
        {{
          detail?.expires_at
            ? dayjs(detail?.expires_at).format("YYYY-MM-DD HH:mm:ss")
            : "-"
        }}
      </el-descriptions-item>
    </el-descriptions>
  </el-drawer>
</template>
