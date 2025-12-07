<template>
  <el-dialog
    :model-value="visible"
    title="创建用户"
    :width="dialogSize"
    @update:model-value="$emit('update:visible', $event)"
  >
    <el-form ref="formRef" :model="form" label-width="100px">
      <el-form-item
        label="邮箱"
        prop="email"
        :rules="[
          { required: true, message: '请输入邮箱', trigger: 'blur' },
          { type: 'email', message: '请输入正确的邮箱格式', trigger: 'blur' }
        ]"
        class="ml-3 mr-3"
      >
        <el-input
          v-model="form.email"
          placeholder="请输入邮箱"
          autocomplete="off"
        />
      </el-form-item>
      <el-form-item
        label="用户名"
        prop="username"
        :rules="[
          {
            min: 3,
            max: 20,
            message: '用户名长度在 3 到 20 个字符',
            trigger: 'blur'
          }
        ]"
        class="ml-3 mr-3"
      >
        <el-input
          v-model="form.username"
          placeholder="不填写将自动生成"
          autocomplete="off"
        />
      </el-form-item>
    </el-form>
    <template #footer>
      <span class="dialog-footer">
        <el-button @click="handleClose">取消</el-button>
        <el-button type="primary" :loading="loading" @click="handleSubmit"
          >创建</el-button
        >
      </span>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from "vue";
import type { FormInstance } from "element-plus";
import { createUser } from "@/api/user";
import { message } from "@shared/utils";
import { useDialogSize } from "@/views/system/dialog";

interface Props {
  visible: boolean;
}

interface Emits {
  (e: "update:visible", value: boolean): void;
  (e: "success"): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

// 使用统一的响应式对话框宽度
const { dialogSize } = useDialogSize();

const formRef = ref<FormInstance>();
const loading = ref(false);

const form = reactive({
  email: "",
  username: ""
});

// 提交表单
const handleSubmit = async () => {
  if (!formRef.value) return;

  try {
    await formRef.value.validate();
    loading.value = true;

    const data = {
      email: form.email,
      username: form.username || undefined
    };

    await createUser(data);
    message("用户创建成功，登录信息已发送到邮箱", { type: "success" });
    handleClose();
    emit("success");
  } catch (error) {
    console.error(error);
  } finally {
    loading.value = false;
  }
};

// 关闭对话框
const handleClose = () => {
  // 重置表单
  Object.assign(form, {
    email: "",
    username: ""
  });

  emit("update:visible", false);
};

// 监听visible变化，关闭时重置表单
watch(
  () => props.visible,
  newVal => {
    if (!newVal) {
      formRef.value?.resetFields();
    }
  }
);
</script>
