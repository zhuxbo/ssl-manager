<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { getCost, updateCost } from "@/api/product";
import { message } from "@shared/utils";

interface Props {
  visible: boolean;
  id: number;
}

// 将成本数据结构改成API返回的结构
interface CostData {
  price: Record<string, number>;
  alternative_standard_price?: Record<string, number>;
  alternative_wildcard_price?: Record<string, number>;
}

const props = defineProps<Props>();
const emit = defineEmits(["update:visible", "success"]);

const loading = ref(false);
const costForm = ref<CostData>({
  price: {},
  alternative_standard_price: {},
  alternative_wildcard_price: {}
});
const periods = ref<number[]>([]);
const alternativeTypes = ref<string[]>([]);

// 使用计算属性解决prop修改问题
const dialogVisible = computed({
  get: () => props.visible,
  set: val => emit("update:visible", val)
});

// 初始化价格配置
const initializeCost = () => {
  const newCost: CostData = {
    price: { ...(costForm.value.price || {}) }
  };

  if (alternativeTypes.value.includes("standard")) {
    newCost.alternative_standard_price = {
      ...(costForm.value.alternative_standard_price || {})
    };
  }

  if (alternativeTypes.value.includes("wildcard")) {
    newCost.alternative_wildcard_price = {
      ...(costForm.value.alternative_wildcard_price || {})
    };
  }

  // 确保所有周期都有对应的价格字段
  periods.value.forEach(period => {
    const periodStr = period.toString();
    if (!newCost.price[periodStr]) {
      newCost.price[periodStr] = 0;
    }

    if (
      alternativeTypes.value.includes("standard") &&
      newCost.alternative_standard_price
    ) {
      if (!newCost.alternative_standard_price[periodStr]) {
        newCost.alternative_standard_price[periodStr] = 0;
      }
    }

    if (
      alternativeTypes.value.includes("wildcard") &&
      newCost.alternative_wildcard_price
    ) {
      if (!newCost.alternative_wildcard_price[periodStr]) {
        newCost.alternative_wildcard_price[periodStr] = 0;
      }
    }
  });

  costForm.value = newCost;
};

// 监听 visible 变化，获取数据
watch(
  () => props.visible,
  async visible => {
    if (visible && props.id > 0) {
      await fetchData();
    }
  }
);

// 监听 id 变化，获取数据
watch(
  () => props.id,
  async id => {
    if (id && props.visible) {
      await fetchData();
    }
  }
);

// 监听 periods 和 alternativeTypes 变化，重新初始化价格配置
watch([() => periods.value, () => alternativeTypes.value], () => {
  initializeCost();
});

// 获取数据
const fetchData = async () => {
  loading.value = true;
  try {
    getCost(props.id).then(({ data }) => {
      periods.value = data.periods || [];
      alternativeTypes.value = data.alternative_name_types || [];

      // 确保API返回的数据符合我们的格式
      const apiCost: CostData = {
        price: {},
        ...(data.cost || {})
      };

      // 确保必须的price属性存在
      if (!apiCost.price) {
        apiCost.price = {};
      }

      costForm.value = apiCost;
      initializeCost();
    });
  } finally {
    loading.value = false;
  }
};

// 关闭弹窗
const handleClose = () => {
  emit("update:visible", false);
};

// 提交表单
const handleConfirm = async () => {
  loading.value = true;

  // 确保提交的价格数据格式正确
  const validCost: CostData = {
    price: { ...costForm.value.price }
  };

  if (
    alternativeTypes.value.includes("standard") &&
    costForm.value.alternative_standard_price
  ) {
    validCost.alternative_standard_price = {
      ...costForm.value.alternative_standard_price
    };
  }

  if (
    alternativeTypes.value.includes("wildcard") &&
    costForm.value.alternative_wildcard_price
  ) {
    validCost.alternative_wildcard_price = {
      ...costForm.value.alternative_wildcard_price
    };
  }

  updateCost(props.id, validCost)
    .then(() => {
      message("更新成本成功", { type: "success" });
      handleClose();
      emit("success");
    })
    .finally(() => {
      loading.value = false;
    });
};
</script>

<template>
  <el-dialog
    v-model="dialogVisible"
    title="编辑成本"
    width="800px"
    destroy-on-close
    @closed="handleClose"
  >
    <div v-loading="loading">
      <el-table :data="periods" border style="width: 100%">
        <el-table-column label="周期" width="100" align="center">
          <template #default="{ row }"> {{ row }}个月 </template>
        </el-table-column>
        <el-table-column label="基础价格" align="center">
          <template #default="{ row }">
            <el-input-number
              v-model="costForm.price[row]"
              :min="0"
              :precision="2"
              controls-position="right"
              style="width: 160px"
            />
          </template>
        </el-table-column>
        <el-table-column
          v-if="alternativeTypes.includes('standard')"
          label="附加标准域名价格"
          align="center"
        >
          <template #default="{ row }">
            <el-input-number
              :model-value="costForm.alternative_standard_price?.[row] || 0"
              :min="0"
              :precision="2"
              controls-position="right"
              style="width: 160px"
              @update:model-value="
                value => {
                  if (!costForm.alternative_standard_price) {
                    costForm.alternative_standard_price = {};
                  }
                  costForm.alternative_standard_price[row] = value;
                }
              "
            />
          </template>
        </el-table-column>
        <el-table-column
          v-if="alternativeTypes.includes('wildcard')"
          label="附加通配符价格"
          align="center"
        >
          <template #default="{ row }">
            <el-input-number
              :model-value="costForm.alternative_wildcard_price?.[row] || 0"
              :min="0"
              :precision="2"
              controls-position="right"
              style="width: 160px"
              @update:model-value="
                value => {
                  if (!costForm.alternative_wildcard_price) {
                    costForm.alternative_wildcard_price = {};
                  }
                  costForm.alternative_wildcard_price[row] = value;
                }
              "
            />
          </template>
        </el-table-column>
      </el-table>
    </div>
    <template #footer>
      <el-button @click="handleClose">取消</el-button>
      <el-button type="primary" :loading="loading" @click="handleConfirm">
        确定
      </el-button>
    </template>
  </el-dialog>
</template>

<style scoped>
:deep(.el-input-number .el-input__wrapper) {
  padding: 0 8px;
}

:deep(.el-table .cell) {
  padding: 8px;
}
</style>
