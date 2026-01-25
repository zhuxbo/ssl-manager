<script setup lang="ts">
import { usePassword } from "./password";
import { PlusForm } from "plus-pro-components";
import { useApi } from "./api";
import { useDeploy } from "./deploy";
import { useCallback } from "./callback";
import { useProfile, VerifyDialog } from "./profile";
import { useNotificationPreference } from "./notification";

defineOptions({
  name: "Setting"
});

const {
  profileColumns,
  profileRules,
  profileValues,
  verifyDialogVisible,
  verifyType,
  verifyCode,
  countdown,
  handleSendCode,
  handleVerifySubmit
} = useProfile();
const {
  passwordColumns,
  passwordRules,
  passwordValues,
  handlePasswordUpdate,
  resetPassword
} = usePassword();
const { apiColumns, apiRules, apiValues, handleApiUpdate, resetApiToken } =
  useApi();
const {
  deployColumns,
  deployRules,
  deployValues,
  handleDeployUpdate,
  resetDeployToken
} = useDeploy();
const {
  callbackColumns,
  callbackRules,
  callbackValues,
  handleCallbackUpdate,
  resetCallback
} = useCallback();
const {
  notificationValues,
  notificationChannels,
  notificationLoading,
  handleToggle
} = useNotificationPreference();
</script>

<template>
  <div class="flex flex-col gap-4">
    <el-card shadow="never" :style="{ border: 'none', paddingTop: '20px' }">
      <PlusForm
        v-model="profileValues"
        :columns="profileColumns"
        :rules="profileRules"
        label-width="100"
        label-position="right"
        label-suffix=""
        :has-footer="false"
      />
    </el-card>
    <el-card shadow="never" :style="{ border: 'none', paddingTop: '20px' }">
      <PlusForm
        v-model="passwordValues"
        :columns="passwordColumns"
        :rules="passwordRules"
        label-width="100"
        label-position="right"
        label-suffix=""
        footer-align="right"
        submit-text="保存"
        reset-text="重置"
        :onSubmit="handlePasswordUpdate"
        :onReset="resetPassword"
      />
    </el-card>
    <el-card shadow="never" :style="{ border: 'none', paddingTop: '20px' }">
      <PlusForm
        v-model="apiValues"
        :columns="apiColumns"
        :rules="apiRules"
        label-width="100"
        label-position="right"
        label-suffix=""
        footer-align="right"
        submit-text="保存"
        reset-text="重置"
        :onSubmit="handleApiUpdate"
        :onReset="resetApiToken"
      />
    </el-card>
    <el-card shadow="never" :style="{ border: 'none', paddingTop: '20px' }">
      <PlusForm
        v-model="deployValues"
        :columns="deployColumns"
        :rules="deployRules"
        label-width="100"
        label-position="right"
        label-suffix=""
        footer-align="right"
        submit-text="保存"
        reset-text="重置"
        :onSubmit="handleDeployUpdate"
        :onReset="resetDeployToken"
      />
    </el-card>
    <el-card shadow="never" :style="{ border: 'none', paddingTop: '20px' }">
      <PlusForm
        v-model="callbackValues"
        :columns="callbackColumns"
        :rules="callbackRules"
        label-width="100"
        label-position="right"
        label-suffix=""
        footer-align="right"
        submit-text="保存"
        reset-text="重置"
        :onSubmit="handleCallbackUpdate"
        :onReset="resetCallback"
      />
    </el-card>
    <el-card shadow="never" :style="{ border: 'none', paddingTop: '20px' }">
      <div class="notification-card__header">
        <div>
          <div class="notification-card__title">通知设置</div>
          <div class="notification-card__desc">
            选择是否接收来自不同渠道的通知提醒
          </div>
        </div>
      </div>
      <el-empty
        v-if="notificationChannels.length === 0"
        description="暂无可配置的通知类型"
      />
      <div
        v-for="channel in notificationChannels"
        v-else
        :key="channel.key"
        class="notification-channel"
      >
        <div class="notification-channel__title">{{ channel.label }}</div>
        <div class="notification-channel__items">
          <div
            v-for="item in channel.items"
            :key="item.type"
            class="notification-item"
          >
            <div class="notification-item__label">
              <span>{{ item.label }}</span>
              <small>{{ item.type }}</small>
            </div>
            <el-switch
              :model-value="notificationValues[channel.key][item.type]"
              :loading="notificationLoading"
              @change="
                val => handleToggle(channel.key, item.type, val as boolean)
              "
            />
          </div>
        </div>
      </div>
    </el-card>
    <VerifyDialog
      :visible="verifyDialogVisible"
      :type="verifyType"
      :countdown="countdown"
      :verifyCode="verifyCode"
      :onSendCode="handleSendCode"
      :onSubmit="handleVerifySubmit"
      :onClose="() => (verifyDialogVisible = false)"
      :onUpdateVerifyCode="val => (verifyCode = val)"
    />
  </div>
</template>

<style scoped lang="scss">
::v-deep(.el-input-group__append) .el-button--primary {
  color: #fff !important;
  background-color: var(--el-color-primary) !important;
  border-color: var(--el-color-primary) !important;
  border-radius: 0 4px 4px 0 !important;
}

::v-deep(.plus-form__footer) {
  display: flex;
  justify-content: flex-end;
}

.notification-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
}

.notification-card__title {
  font-size: 16px;
  font-weight: 600;
  color: var(--el-text-color-primary);
}

.notification-card__desc {
  margin-top: 4px;
  font-size: 13px;
  color: var(--el-text-color-secondary);
}

.notification-channel + .notification-channel {
  margin-top: 24px;
}

.notification-channel__title {
  margin-bottom: 12px;
  font-weight: 600;
}

.notification-channel__items {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 12px;
}

.notification-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: var(--el-bg-color);
  border: 1px solid var(--el-border-color);
  border-radius: 8px;
}

.notification-item__label {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.notification-item__label small {
  font-size: 12px;
  color: var(--el-text-color-secondary);
}
</style>
