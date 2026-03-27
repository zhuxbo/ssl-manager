<template>
  <div class="document-upload">
    <!-- 本地文档列表 -->
    <div
      v-if="documents.length > 0"
      style="margin: 10px 0 8px; font-size: 13px; font-weight: 500"
    >
      本地文档<span
        style="
          font-size: 12px;
          font-weight: normal;
          color: var(--el-text-color-secondary);
        "
        >（签发后 24 小时内删除）</span
      >
    </div>
    <el-table
      v-if="documents.length > 0"
      :data="documents"
      size="small"
      style="width: 100%"
    >
      <el-table-column prop="file_name" label="文件名" min-width="200">
        <template #default="{ row }">
          <el-tooltip
            :content="row.description"
            :disabled="!row.description"
            placement="top"
          >
            <el-link type="primary" @click="handlePreview(row.id)">
              {{ row.file_name }}
            </el-link>
          </el-tooltip>
        </template>
      </el-table-column>
      <el-table-column prop="type" label="类型" width="120">
        <template #default="{ row }">
          {{ documentTypes[row.type] || row.type }}
        </template>
      </el-table-column>
      <el-table-column prop="file_size" label="大小" width="80">
        <template #default="{ row }">
          {{ formatSize(row.file_size) }}
        </template>
      </el-table-column>
      <el-table-column prop="uploaded_by" label="来源" width="60">
        <template #default="{ row }">
          {{
            { user: "用户", admin: "管理", api: "API" }[row.uploaded_by] ||
            row.uploaded_by
          }}
        </template>
      </el-table-column>
      <el-table-column prop="submitted" label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="row.submitted ? 'success' : 'warning'" size="small">
            {{ row.submitted ? "已提交" : "待提交" }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="60">
        <template #default="{ row }">
          <el-button
            size="small"
            type="danger"
            link
            @click="handleDelete(row.id)"
          >
            删除
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <div style="display: flex; gap: 8px; margin-top: 8px">
      <el-button
        v-if="unsubmittedCount > 0"
        size="small"
        type="success"
        :loading="submitting"
        @click="handleSubmit"
      >
        提交 ({{ unsubmittedCount }})
      </el-button>
      <el-button size="small" type="primary" @click="showUploadDialog = true">
        上传文档
      </el-button>
    </div>

    <!-- 上传对话框 -->
    <el-dialog v-model="showUploadDialog" title="上传验证文档" width="480px">
      <el-form label-width="80px">
        <el-form-item label="文档类型">
          <el-select
            v-model="uploadForm.type"
            placeholder="请选择文档类型"
            style="width: 100%"
          >
            <el-option
              v-for="(label, key) in documentTypes"
              :key="key"
              :label="label"
              :value="key"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="描述">
          <el-input
            v-model="uploadForm.description"
            placeholder="可选"
            maxlength="255"
          />
        </el-form-item>
        <el-form-item label="文件">
          <el-upload
            ref="uploadRef"
            :auto-upload="false"
            :limit="1"
            :on-change="handleFileChange"
            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
          >
            <el-button size="small" type="primary">选择文件</el-button>
            <template #tip>
              <div class="el-upload__tip">
                支持 PDF/JPG/PNG/DOC/DOCX，不超过 5MB
              </div>
            </template>
          </el-upload>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showUploadDialog = false">取消</el-button>
        <el-button
          type="primary"
          :loading="uploading"
          :disabled="!uploadForm.type || !uploadForm.file"
          @click="handleUpload"
        >
          上传
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, inject, computed, onMounted } from "vue";
import {
  uploadDocument,
  getDocuments,
  deleteDocument,
  submitDocuments,
  previewDocument
} from "@/api/order";
import { ElMessage, ElMessageBox } from "element-plus";
import type { UploadFile } from "element-plus";

const order = inject("order") as any;

const documentTypes: Record<string, string> = {
  APPLICANT: "申请人文档",
  ORGANIZATION: "企业文档",
  AUTHORIZATION: "授权文档",
  ADDITIONAL: "附加文档"
};

const documents = ref<any[]>([]);
const showUploadDialog = ref(false);
const uploading = ref(false);
const submitting = ref(false);
const uploadRef = ref();

const uploadForm = ref({
  type: "",
  description: "",
  file: null as File | null
});

const unsubmittedCount = computed(
  () => documents.value.filter(d => !d.submitted).length
);

const formatSize = (bytes: number) => {
  if (bytes < 1024) return bytes + "B";
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + "KB";
  return (bytes / 1024 / 1024).toFixed(1) + "MB";
};

const handlePreview = async (docId: number) => {
  const win = window.open("", "_blank");
  try {
    const url = await previewDocument(docId);
    if (win) win.location.href = url;
    setTimeout(() => URL.revokeObjectURL(url), 60000);
  } catch {
    win?.close();
  }
};

const loadDocuments = async () => {
  const res = await getDocuments(order.id);
  if (res.code === 1) {
    documents.value = res.data || [];
  }
};

const handleFileChange = (file: UploadFile) => {
  uploadForm.value.file = file.raw || null;
};

const handleUpload = async () => {
  if (!uploadForm.value.file) return;

  uploading.value = true;
  try {
    const formData = new FormData();
    formData.append("file", uploadForm.value.file);
    formData.append("type", uploadForm.value.type);
    if (uploadForm.value.description) {
      formData.append("description", uploadForm.value.description);
    }

    const res = await uploadDocument(order.id, formData);
    if (res.code === 1) {
      ElMessage.success("上传成功");
      showUploadDialog.value = false;
      uploadForm.value = { type: "APPLICANT", description: "", file: null };
      uploadRef.value?.clearFiles();
      await loadDocuments();
    }
  } finally {
    uploading.value = false;
  }
};

const handleDelete = async (docId: number) => {
  await ElMessageBox.confirm("确定删除此文档？", "提示", { type: "warning" });
  const res = await deleteDocument(docId);
  if (res.code === 1) {
    ElMessage.success("删除成功");
    await loadDocuments();
  }
};

const handleSubmit = async () => {
  await ElMessageBox.confirm("确定提交所有待提交文档到上游？", "提示", {
    type: "warning"
  });
  submitting.value = true;
  try {
    const res = await submitDocuments(order.id);
    if (res.code === 1) {
      ElMessage.success("提交成功");
      await loadDocuments();
    }
  } finally {
    submitting.value = false;
  }
};

onMounted(loadDocuments);
</script>

<style scoped lang="scss">
.document-upload {
  margin: 10px 0;
}

.section-header {
  display: flex;
  gap: 10px;
  align-items: center;
  margin-bottom: 10px;
}

.section-title {
  font-size: 13px;
  font-weight: 500;
  color: var(--el-text-color-primary);
}
</style>
