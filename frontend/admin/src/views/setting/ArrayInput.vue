<template>
  <div class="array-input">
    <div class="array-input-header flex justify-between mb-2">
      <div class="array-input-tabs">
        <el-radio-group v-model="inputMode" size="small">
          <el-radio-button value="array">列表模式</el-radio-button>
          <el-radio-button value="object">键值对模式</el-radio-button>
        </el-radio-group>
      </div>
    </div>

    <!-- 列表模式 -->
    <div v-if="inputMode === 'array'">
      <div
        v-for="(item, index) in arrayItems"
        :key="index"
        class="array-item flex items-center mb-2"
      >
        <el-input
          v-model="arrayItems[index]"
          :placeholder="`项目 ${index + 1}`"
          @change="updateArray"
        >
          <template #prepend>{{ index + 1 }}</template>
        </el-input>
        <el-button
          type="danger"
          size="small"
          plain
          class="ml-2"
          @click="removeArrayItem(index)"
        >
          <el-icon><Delete /></el-icon>
        </el-button>
      </div>
      <el-button
        type="primary"
        size="small"
        plain
        class="mt-2"
        @click="addArrayItem"
      >
        添加项目
      </el-button>
    </div>

    <!-- 键值对模式 -->
    <div v-else-if="inputMode === 'object'">
      <div
        v-for="(_item, index) in objectItems"
        :key="index"
        class="object-item flex items-center mb-2 gap-2"
      >
        <el-input
          v-model="objectItems[index].key"
          placeholder="键名"
          style="width: 40%"
          @change="updateObject"
        />
        <el-input
          v-model="objectItems[index].value"
          placeholder="值"
          style="width: 60%"
          @change="updateObject"
        />
        <el-button
          type="danger"
          size="small"
          plain
          @click="removeObjectItem(index)"
        >
          <el-icon><Delete /></el-icon>
        </el-button>
      </div>
      <el-button
        type="primary"
        size="small"
        plain
        class="mt-2"
        @click="addObjectItem"
      >
        添加键值对
      </el-button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from "vue";
import { Delete } from "@element-plus/icons-vue";

const props = defineProps({
  modelValue: {
    type: [Array, Object],
    default: () => []
  }
});

const emit = defineEmits(["update:modelValue", "change"]);

// 输入模式：array 或 object
const inputMode = ref("array");
const arrayItems = ref([]);
const objectItems = ref([]);

// 初始化数据
const initData = () => {
  const value = props.modelValue;

  if (Array.isArray(value)) {
    inputMode.value = "array";
    arrayItems.value = [...value];
  } else if (typeof value === "object" && value !== null) {
    inputMode.value = "object";
    objectItems.value = Object.keys(value).map(key => ({
      key,
      value: value[key]
    }));
  } else {
    // 默认初始化为空数组
    arrayItems.value = [];
    objectItems.value = [];
  }
};

// 更新数组
const updateArray = () => {
  emit("update:modelValue", arrayItems.value);
  emit("change", arrayItems.value);
};

// 更新对象
let nextId = 0; // 为键值对添加唯一id
const updateObject = () => {
  const obj = {};
  objectItems.value.forEach(item => {
    const key = item.key.trim();
    if (key) {
      // 判断建名是否已存在
      if (obj.hasOwnProperty(key)) {
        obj[key + "_" + nextId] = item.value;
        nextId++;
      } else {
        obj[key] = item.value;
      }
    } else {
      obj["temp_" + nextId] = item.value;
      nextId++;
    }
  });

  emit("update:modelValue", obj);
  emit("change", obj);
};

// 添加数组项
const addArrayItem = () => {
  arrayItems.value.push("");
  updateArray();
};

// 移除数组项
const removeArrayItem = index => {
  arrayItems.value.splice(index, 1);
  updateArray();
};

// 添加对象项
const addObjectItem = () => {
  objectItems.value.push({ key: "", value: "" });
  updateObject();
};

// 移除对象项
const removeObjectItem = index => {
  objectItems.value.splice(index, 1);
  updateObject();
};

// 监听输入模式变化
watch(inputMode, () => {
  if (inputMode.value === "array") {
    const value = props.modelValue;
    if (Array.isArray(value)) {
      arrayItems.value = [...value];
    } else if (typeof value === "object" && value !== null) {
      // 如果是对象，将其转换为数组
      arrayItems.value = Object.values(value);
    }
    updateArray();
  } else {
    const value = props.modelValue;
    if (typeof value === "object" && !Array.isArray(value) && value !== null) {
      objectItems.value = Object.keys(value).map(key => ({
        key,
        value: value[key]
      }));
    } else if (Array.isArray(value)) {
      // 如果是数组，将其转换为对象
      objectItems.value = value.map((item, index) => ({
        key: String(index),
        value: item
      }));
    }
    updateObject();
  }
});

// 监听props变化
watch(
  () => props.modelValue,
  newVal => {
    if (newVal) {
      initData();
    }
  },
  { deep: true }
);

onMounted(() => {
  initData();
});
</script>

<style scoped lang="scss">
.array-input {
  width: 100%;
}
</style>
