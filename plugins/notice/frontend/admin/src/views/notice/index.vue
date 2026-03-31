<script setup lang="ts">
import { ref, reactive, onMounted } from "vue";
import { ElMessage, ElMessageBox } from "element-plus";
import dayjs from "dayjs";
import * as noticeApi from "../../api/notice";

defineOptions({ name: "Notice" });

const loading = ref(false);
const dataList = ref<any[]>([]);
const pagination = reactive({
  currentPage: 1,
  pageSize: 15,
  total: 0
});

const typeOptions = [
  { label: "信息", value: "info" },
  { label: "警告", value: "warning" },
  { label: "成功", value: "success" },
  { label: "危险", value: "error" }
];

const positionOptions = [
  { label: "首页", value: "dashboard" },
  { label: "订单页", value: "order" },
  { label: "购买页", value: "product" },
  { label: "弹窗", value: "popup" }
];

const positionLabelMap: Record<string, string> = {
  dashboard: "首页",
  order: "订单页",
  product: "购买页",
  popup: "弹窗"
};

const typeTagMap: Record<string, string> = {
  info: "info",
  warning: "warning",
  success: "success",
  error: "danger"
};

// Dialog
const dialogVisible = ref(false);
const dialogTitle = ref("新建公告");
const editingId = ref<number | null>(null);
const form = reactive({
  title: "",
  content: "",
  type: "info",
  position: "dashboard",
  sort: 0
});

const fetchList = async () => {
  loading.value = true;
  try {
    const { data } = await noticeApi.getList({
      currentPage: pagination.currentPage,
      pageSize: pagination.pageSize
    });
    dataList.value = data.data;
    pagination.total = data.total;
  } catch {
    ElMessage.error("获取公告列表失败");
  } finally {
    loading.value = false;
  }
};

const handleSizeChange = (size: number) => {
  pagination.pageSize = size;
  pagination.currentPage = 1;
  fetchList();
};

const handleCurrentChange = (page: number) => {
  pagination.currentPage = page;
  fetchList();
};

const openCreate = () => {
  editingId.value = null;
  dialogTitle.value = "新建公告";
  form.title = "";
  form.content = "";
  form.type = "info";
  form.position = "dashboard";
  form.sort = 0;
  dialogVisible.value = true;
};

const openEdit = (row: any) => {
  editingId.value = row.id;
  dialogTitle.value = "编辑公告";
  form.title = row.title;
  form.content = row.content;
  form.type = row.type;
  form.position = row.position;
  form.sort = row.sort;
  dialogVisible.value = true;
};

const submitForm = async () => {
  if (!form.title.trim()) {
    ElMessage.warning("请输入标题");
    return;
  }
  if (!form.content.trim()) {
    ElMessage.warning("请输入内容");
    return;
  }
  try {
    if (editingId.value) {
      await noticeApi.update(editingId.value, { ...form });
      ElMessage.success("更新成功");
    } else {
      await noticeApi.create({ ...form });
      ElMessage.success("创建成功");
    }
    dialogVisible.value = false;
    fetchList();
  } catch {
    ElMessage.error("操作失败");
  }
};

const handleToggle = async (row: any) => {
  try {
    await noticeApi.toggle(row.id);
    ElMessage.success(row.is_active ? "已停用" : "已启用");
    fetchList();
  } catch {
    ElMessage.error("操作失败");
  }
};

const handleDelete = (row: any) => {
  ElMessageBox.confirm(`确定删除公告「${row.title}」吗？`, "确认", {
    type: "warning"
  }).then(async () => {
    try {
      await noticeApi.remove(row.id);
      ElMessage.success("删除成功");
      fetchList();
    } catch {
      ElMessage.error("删除失败");
    }
  });
};

const formatTime = (val: string) =>
  val ? dayjs(val).format("YYYY-MM-DD HH:mm") : "";

onMounted(() => fetchList());
</script>

<template>
  <div class="main">
    <div class="bg-bg_color w-[99/100] p-4">
      <div class="flex justify-between items-center mb-4">
        <el-button type="primary" @click="openCreate">新建公告</el-button>
      </div>

      <el-table
        v-loading="loading"
        :data="dataList"
        table-layout="auto"
        :header-cell-style="{
          background: 'var(--el-fill-color-light)',
          color: 'var(--el-text-color-primary)'
        }"
      >
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="title" label="标题" min-width="200" />
        <el-table-column prop="type" label="类型" width="100">
          <template #default="{ row }">
            <el-tag :type="typeTagMap[row.type] || ''" size="small">
              {{
                typeOptions.find(t => t.value === row.type)?.label || row.type
              }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="position" label="显示位置" width="100">
          <template #default="{ row }">
            {{ positionLabelMap[row.position] || row.position }}
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" width="80" />
        <el-table-column prop="is_active" label="状态" width="100">
          <template #default="{ row }">
            <el-switch
              :model-value="row.is_active"
              @change="handleToggle(row)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="170">
          <template #default="{ row }">
            {{ formatTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="openEdit(row)">
              编辑
            </el-button>
            <el-button
              link
              type="danger"
              size="small"
              @click="handleDelete(row)"
            >
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="flex justify-end mt-4">
        <el-pagination
          v-model:current-page="pagination.currentPage"
          v-model:page-size="pagination.pageSize"
          :total="pagination.total"
          :page-sizes="[15, 30, 50]"
          layout="total, sizes, prev, pager, next"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </div>

    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="550px"
      destroy-on-close
    >
      <el-form label-width="80px">
        <el-form-item label="标题">
          <el-input
            v-model="form.title"
            maxlength="200"
            show-word-limit
            placeholder="请输入公告标题"
          />
        </el-form-item>
        <el-form-item label="内容">
          <el-input
            v-model="form.content"
            type="textarea"
            :rows="5"
            maxlength="5000"
            show-word-limit
            placeholder="请输入公告内容"
          />
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="form.type" class="w-full">
            <el-option
              v-for="o in typeOptions"
              :key="o.value"
              :label="o.label"
              :value="o.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="显示位置">
          <el-select v-model="form.position" class="w-full">
            <el-option
              v-for="o in positionOptions"
              :key="o.value"
              :label="o.label"
              :value="o.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort" :min="0" :max="9999" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitForm">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>
