<template>
  <el-dialog
    v-model="visible"
    title="CNAME 配置指引"
    :width="dialogWidth"
    draggable
    @closed="handleClosed"
  >
    <div class="cname-guide-content">
      <el-alert type="info" :closable="false" style="margin-bottom: 20px">
        请在 DNS 服务商处添加以下 CNAME 记录：
      </el-alert>

      <el-descriptions :column="1" border label-width="6em">
        <el-descriptions-item label="主机记录">
          <span>{{ hostRecord }}</span>
          <el-button link type="primary" @click="copyText(hostRecord)">
            复制
          </el-button>
        </el-descriptions-item>
        <el-descriptions-item label="记录类型"> CNAME </el-descriptions-item>
        <el-descriptions-item label="记录值">
          <span class="break-all whitespace-normal">
            {{ cnameValue }}
          </span>
          <el-button link type="primary" @click="copyText(cnameValue)">
            复制
          </el-button>
        </el-descriptions-item>
      </el-descriptions>

      <el-alert type="warning" :closable="false" style="margin-top: 20px">
        配置后，请等待 DNS 解析生效（通常 5-10 分钟），之后使用 TXT
        解析验证的订单会自动进行验证
      </el-alert>
    </div>

    <template #footer>
      <el-button @click="copyAll">一键复制全部</el-button>
      <el-button type="primary" @click="visible = false"> 知道了 </el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { ElMessage } from "element-plus";
import { parse, type ParsedDomain } from "psl";
import type { CnameGuideProps } from "./types";

const props = withDefaults(defineProps<CnameGuideProps>(), {
  modelValue: false,
  options: null
});

const emit = defineEmits<{
  (e: "update:modelValue", value: boolean): void;
}>();

const visible = ref(props.modelValue);

// 监听外部传入的 modelValue
watch(
  () => props.modelValue,
  newVal => {
    visible.value = newVal;
  }
);

// 监听内部 visible 变化，同步到外部
watch(visible, newVal => {
  emit("update:modelValue", newVal);
});

// 计算弹框宽度
const dialogWidth = computed(() => {
  const width = window.innerWidth;
  if (width < 768) {
    return "95vw";
  } else if (width < 1024) {
    return "80vw";
  } else if (width < 1440) {
    return "720px";
  } else {
    return "720px";
  }
});

// 计算主机记录
const hostRecord = computed(() => {
  if (!props.options?.cname_to || !props.options?.zone) {
    return "";
  }
  return (parse(props.options.cname_to.host) as ParsedDomain)?.subdomain || "";
});

// 获取 CNAME 值
const cnameValue = computed(() => {
  return props.options?.cname_to?.value || "";
});

/**
 * 复制文本到剪贴板
 */
const copyText = (text: string): void => {
  navigator.clipboard
    .writeText(text)
    .then(() => {
      ElMessage.success("复制成功");
    })
    .catch(() => {
      ElMessage.error("复制失败");
    });
};

/**
 * 一键复制全部
 */
const copyAll = (): void => {
  const text = `域名: ${props.options?.zone}\n主机记录: ${hostRecord.value}\n记录类型: CNAME\n记录值: ${cnameValue.value}`;
  navigator.clipboard
    .writeText(text)
    .then(() => {
      ElMessage.success("复制成功");
    })
    .catch(() => {
      ElMessage.error("复制失败");
    });
};

/**
 * 对话框关闭后的回调
 */
const handleClosed = (): void => {
  // 可以在这里做一些清理工作
};
</script>

<style scoped>
.cname-guide-content {
  padding: 0;
}

.break-all {
  word-break: break-all;
}

.whitespace-normal {
  white-space: normal;
}
</style>
