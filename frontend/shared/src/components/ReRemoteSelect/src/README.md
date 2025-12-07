# ReRemoteSelect 远程选择组件

这是一个基于 Element Plus 的远程搜索选择组件，支持分页、搜索、多选等功能。

## 功能特点

- 支持远程搜索
- 支持分页加载
- 支持单选和多选
- 支持初始值的自动加载
- 支持通过 refreshKey 控制组件重置
- 当只有一页数据时自动隐藏分页

## 属性

| 属性名         | 类型             | 默认值   | 说明                                 |
| -------------- | ---------------- | -------- | ------------------------------------ |
| modelValue     | any              | -        | 绑定值，支持单个值或数组（多选模式） |
| uri            | string           | -        | 远程搜索API地址                      |
| searchField    | string           | -        | 搜索字段名                           |
| labelField     | string           | 'name'   | 显示的标签字段名                     |
| valueField     | string           | 'id'     | 值字段名                             |
| placeholder    | string           | '请选择' | 占位符                               |
| pageSize       | number           | 8        | 每页数量                             |
| showPagination | boolean          | true     | 是否显示分页                         |
| queryParams    | object           | {}       | 额外的查询参数                       |
| itemsField     | string           | 'items'  | 返回数据中的列表字段名               |
| totalField     | string           | 'total'  | 返回数据中的总数字段名               |
| refreshKey     | string \| number | -        | 用于控制组件重置的唯一标识           |
| multiple       | boolean          | false    | 是否支持多选                         |

## 事件

| 事件名            | 说明               | 回调参数 |
| ----------------- | ------------------ | -------- |
| update:modelValue | 当选中值变化时触发 | 选中的值 |
| change            | 当选中值变化时触发 | 选中的值 |

## 使用示例

### 基本用法

```vue
<template>
  <ReRemoteSelect
    v-model="selectedValue"
    uri="/api/users"
    search-field="name"
    placeholder="请选择用户"
  />
</template>

<script setup>
import { ref } from "vue";

const selectedValue = ref(null);
</script>
```

### 多选模式

```vue
<template>
  <ReRemoteSelect
    v-model="selectedValues"
    uri="/api/users"
    search-field="name"
    placeholder="请选择多个用户"
    multiple
  />
</template>

<script setup>
import { ref } from "vue";

const selectedValues = ref([]);
</script>
```

### 带初始值

```vue
<template>
  <ReRemoteSelect
    v-model="selectedValue"
    uri="/api/users"
    search-field="name"
    :refresh-key="refreshKey"
  />
</template>

<script setup>
import { ref } from "vue";

const selectedValue = ref(1); // 初始值为ID为1的用户
const refreshKey = ref(1);

// 当需要重置组件时
function resetComponent() {
  refreshKey.value++;
}
</script>
```

### 带额外查询参数

```vue
<template>
  <ReRemoteSelect
    v-model="selectedValue"
    uri="/api/users"
    search-field="name"
    :query-params="queryParams"
  />
</template>

<script setup>
import { ref, reactive } from "vue";

const selectedValue = ref(null);
const queryParams = reactive({
  status: "active",
  role: "admin"
});
</script>
```

## 注意事项

1. 组件会在以下情况下重新加载数据：

   - 首次打开下拉框
   - 搜索条件变化
   - 页码变化
   - refreshKey 变化

2. 当 refreshKey 变化时，组件会重置页码和搜索参数，并重新加载数据。

3. 当有初始值时，组件会自动加载选项名称。只有当 refreshKey 未设置或大于0时才会处理初始值。

4. 当只有一页数据时，分页组件会自动隐藏。
