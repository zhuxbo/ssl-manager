<template>
  <el-dialog
    v-model="visible"
    title=""
    :width="'90%'"
    :before-close="handleClose"
    append-to-body
    class="max-w-[700px] mx-auto"
    :show-close="true"
    :style="{ '--el-dialog-padding-primary': '12px' }"
  >
    <template #header>
      <div class="text-left">
        <h4 class="text-lg font-semibold text-gray-800 mt-2 ml-5">用户信息</h4>
      </div>
    </template>

    <!-- 加载状态 -->
    <div v-if="loading" class="p-5">
      <el-skeleton :rows="8" animated />
    </div>

    <!-- 用户信息内容 -->
    <div v-else-if="userInfo" class="flex flex-col h-[70vh] max-h-[600px]">
      <div class="flex-1 overflow-y-auto px-5 pb-0">
        <!-- 用户基本信息区域 -->
        <div class="mb-5">
          <div
            class="flex items-center gap-2 pb-2 mb-3 text-sm font-semibold text-gray-600 border-b border-gray-200"
          >
            <el-icon><User /></el-icon>
            基本信息
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-2 md:gap-4">
            <div class="flex items-center min-h-[28px]">
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >用户ID：</span
              >
              <span class="flex-1 text-gray-800 break-all">{{
                userInfo.id || "-"
              }}</span>
            </div>
            <div class="flex items-center min-h-[28px]">
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >用户名：</span
              >
              <span class="flex-1 text-gray-800 break-all">{{
                userInfo.username || "-"
              }}</span>
            </div>
            <div v-if="userInfo.email" class="flex items-center min-h-[28px]">
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >邮箱：</span
              >
              <span class="flex-1 text-gray-800 break-all">{{
                userInfo.email
              }}</span>
            </div>
            <div v-if="userInfo.mobile" class="flex items-center min-h-[28px]">
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >手机号：</span
              >
              <span class="flex-1 text-gray-800 break-all">{{
                userInfo.mobile
              }}</span>
            </div>
            <div v-if="userInfo.join_ip" class="flex items-center min-h-[28px]">
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >注册IP：</span
              >
              <span class="flex-1 text-gray-800 break-all">{{
                userInfo.join_ip
              }}</span>
            </div>
            <div v-if="userInfo.source" class="flex items-center min-h-[28px]">
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >来源：</span
              >
              <span class="flex-1 text-gray-800 break-all">{{
                userInfo.source
              }}</span>
            </div>
            <div
              v-if="userInfo.status !== undefined"
              class="flex items-center min-h-[28px]"
            >
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >状态：</span
              >
              <span class="flex-1">
                <el-tag :type="userInfo.status === 1 ? 'success' : 'danger'">
                  {{ userInfo.status === 1 ? "正常" : "禁用" }}
                </el-tag>
              </span>
            </div>
          </div>
        </div>

        <!-- 验证状态区域 -->
        <div class="mb-5">
          <div
            class="flex items-center gap-2 pb-2 mb-3 text-sm font-semibold text-gray-600 border-b border-gray-200"
          >
            <el-icon><Lock /></el-icon>
            验证状态
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-2 md:gap-4">
            <div class="flex items-center min-h-[28px]">
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >邮箱验证：</span
              >
              <span class="flex-1">
                <el-tag
                  :type="userInfo.email_verified_at ? 'success' : 'warning'"
                >
                  {{ userInfo.email_verified_at ? "已验证" : "未验证" }}
                </el-tag>
                <span
                  v-if="userInfo.email_verified_at"
                  class="ml-2 text-xs text-gray-400"
                >
                  {{ formatDate(userInfo.email_verified_at) }}
                </span>
              </span>
            </div>
            <div class="flex items-center min-h-[28px]">
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >手机验证：</span
              >
              <span class="flex-1">
                <el-tag
                  :type="userInfo.mobile_verified_at ? 'success' : 'warning'"
                >
                  {{ userInfo.mobile_verified_at ? "已验证" : "未验证" }}
                </el-tag>
                <span
                  v-if="userInfo.mobile_verified_at"
                  class="ml-2 text-xs text-gray-400"
                >
                  {{ formatDate(userInfo.mobile_verified_at) }}
                </span>
              </span>
            </div>
          </div>
        </div>

        <!-- 资金信息区域 -->
        <div class="mb-5">
          <div
            class="flex items-center gap-2 pb-2 mb-3 text-sm font-semibold text-gray-600 border-b border-gray-200"
          >
            <el-icon><Money /></el-icon>
            资金信息
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-2 md:gap-4">
            <div class="flex items-center min-h-[28px]">
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >余额：</span
              >
              <span class="flex-1 font-semibold text-gray-800">
                ¥{{ userInfo.balance || "0.00" }}
              </span>
            </div>
            <div
              v-if="userInfo.credit_limit !== undefined"
              class="flex items-center min-h-[28px]"
            >
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >信用额度：</span
              >
              <span class="flex-1 text-gray-800">
                ¥{{ Math.abs(userInfo.credit_limit || 0).toFixed(2) }}
              </span>
            </div>
            <div
              v-if="userInfo.invoice_limit !== undefined"
              class="flex items-center min-h-[28px]"
            >
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >发票额度：</span
              >
              <span class="flex-1 font-semibold text-gray-800">
                ¥{{ userInfo.invoice_limit || "0.00" }}
              </span>
            </div>
          </div>
        </div>

        <!-- 等级信息区域 -->
        <div
          v-if="
            userInfo.level ||
            userInfo.custom_level ||
            userInfo.level_code ||
            userInfo.custom_level_code
          "
          class="mb-5"
        >
          <div
            class="flex items-center gap-2 pb-2 mb-3 text-sm font-semibold text-gray-600 border-b border-gray-200"
          >
            <el-icon><Trophy /></el-icon>
            等级信息
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-2 md:gap-4">
            <div
              v-if="userInfo.level || userInfo.level_code"
              class="flex items-center min-h-[28px]"
            >
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >用户级别：</span
              >
              <span class="flex-1 text-gray-800">
                {{ userInfo.level?.name || userInfo.level_code || "-" }}
                <span
                  v-if="userInfo.level_code"
                  class="ml-2 text-xs text-gray-400"
                >
                  ({{ userInfo.level_code }})
                </span>
              </span>
            </div>
            <div
              v-if="userInfo.custom_level || userInfo.custom_level_code"
              class="flex items-center min-h-[28px]"
            >
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >定制级别：</span
              >
              <span class="flex-1 text-gray-800">
                {{
                  userInfo.custom_level?.name ||
                  userInfo.custom_level_code ||
                  "-"
                }}
                <span
                  v-if="userInfo.custom_level_code"
                  class="ml-2 text-xs text-gray-400"
                >
                  ({{ userInfo.custom_level_code }})
                </span>
              </span>
            </div>
          </div>
        </div>

        <!-- 登录信息区域 -->
        <div class="mb-5">
          <div
            class="flex items-center gap-2 pb-2 mb-3 text-sm font-semibold text-gray-600 border-b border-gray-200"
          >
            <el-icon><Clock /></el-icon>
            时间信息
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-2 md:gap-4">
            <div
              v-if="userInfo.last_login_at"
              class="flex items-center min-h-[28px]"
            >
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >最后登录时间：</span
              >
              <span class="flex-1 text-gray-800">{{
                formatDate(userInfo.last_login_at)
              }}</span>
            </div>
            <div
              v-if="userInfo.last_login_ip"
              class="flex items-center min-h-[28px]"
            >
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >最后登录IP：</span
              >
              <span class="flex-1 text-gray-800">{{
                userInfo.last_login_ip
              }}</span>
            </div>
            <div
              v-if="userInfo.logout_at"
              class="flex items-center min-h-[28px]"
            >
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >最后登出时间：</span
              >
              <span class="flex-1 text-gray-800">{{
                formatDate(userInfo.logout_at)
              }}</span>
            </div>
            <div v-if="userInfo.join_at" class="flex items-center min-h-[28px]">
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >注册时间：</span
              >
              <span class="flex-1 text-gray-800">{{
                formatDate(userInfo.join_at)
              }}</span>
            </div>
            <div
              v-if="userInfo.created_at"
              class="flex items-center min-h-[28px]"
            >
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >创建时间：</span
              >
              <span class="flex-1 text-gray-800">{{
                formatDate(userInfo.created_at)
              }}</span>
            </div>
            <div
              v-if="userInfo.updated_at"
              class="flex items-center min-h-[28px]"
            >
              <span
                class="min-w-[80px] md:min-w-[80px] font-medium text-gray-500 text-right md:text-right"
                >更新时间：</span
              >
              <span class="flex-1 text-gray-800">{{
                formatDate(userInfo.updated_at)
              }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 操作按钮区域 -->
      <div
        class="flex flex-col md:flex-row gap-y-2 md:gap-x-3 py-4 px-0 md:px-4 border-t border-gray-200 bg-gray-50 flex-shrink-0"
      >
        <el-button
          type="success"
          class="w-full md:flex-1 !m-0"
          @click="handleDirectLogin"
        >
          <el-icon class="mr-2"><User /></el-icon>
          登录会员中心
        </el-button>
        <el-button
          type="primary"
          class="w-full md:flex-1 !m-0"
          @click="handleUserManagement"
        >
          <el-icon class="mr-2"><Setting /></el-icon>
          用户管理
        </el-button>
        <el-button
          type="warning"
          class="w-full md:flex-1 !m-0"
          @click="handleRecharge"
        >
          <el-icon class="mr-2"><Money /></el-icon>
          资金管理
        </el-button>
      </div>
    </div>

    <!-- 错误状态 -->
    <div v-else-if="error" class="p-5 text-center">
      <el-result icon="error" title="加载失败" :sub-title="error">
        <template #extra>
          <el-button type="primary" @click="fetchUserInfo">重试</el-button>
        </template>
      </el-result>
    </div>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { ElMessage } from "element-plus";
import {
  User,
  Setting,
  Money,
  Lock,
  Trophy,
  Clock
} from "@element-plus/icons-vue";
import * as userApi from "@/api/user";
import dayjs from "dayjs";

interface Props {
  modelValue: boolean;
  userId: number | null;
}

interface Emits {
  (e: "update:modelValue", value: boolean): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();
const router = useRouter();

const userInfo = ref<any>(null);
const loading = ref(false);
const error = ref<string>("");

const visible = computed({
  get: () => props.modelValue,
  set: value => emit("update:modelValue", value)
});

const handleClose = () => {
  visible.value = false;
};

const formatDate = (date: string | null) => {
  return date ? dayjs(date).format("YYYY-MM-DD HH:mm:ss") : "-";
};

// 获取用户信息
const fetchUserInfo = async () => {
  if (!props.userId) return;

  loading.value = true;
  error.value = "";

  try {
    const { data } = await userApi.show(props.userId);
    userInfo.value = data;
  } catch (err: any) {
    error.value = err.message || "获取用户信息失败";
    userInfo.value = null;
  } finally {
    loading.value = false;
  }
};

// 监听对话框显示状态
watch(
  () => props.modelValue,
  isVisible => {
    if (!isVisible) {
      // 对话框关闭时清空错误状态
      error.value = "";
    } else {
      // 对话框打开时重新获取用户信息
      if (props.userId) {
        fetchUserInfo();
      }
    }
  }
);

// 直接登录到会员中心
const handleDirectLogin = async () => {
  if (!props.userId) {
    ElMessage.error("用户信息无效");
    return;
  }

  try {
    const { data } = await userApi.directLogin(props.userId);
    if (data.direct_login_url) {
      window.open(data.direct_login_url, "_blank");
      ElMessage.success("正在跳转到会员中心...");
    } else {
      ElMessage.error("获取登录链接失败");
    }
  } catch (error) {
    ElMessage.error("直接登录失败");
  }
};

// 跳转到用户管理页面
const handleUserManagement = () => {
  const username = userInfo.value?.username;
  if (username) {
    router.push({
      path: "/user",
      query: { username }
    });
  }
  handleClose();
};

// 跳转到资金管理页面
const handleRecharge = () => {
  const username = userInfo.value?.username;
  if (username) {
    router.push({
      path: "/funds",
      query: { username }
    });
  }
  handleClose();
};
</script>
