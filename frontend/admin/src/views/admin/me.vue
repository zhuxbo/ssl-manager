<script setup lang="ts">
import { useAdminProfile } from "./profile";
import { useAdminPassword } from "./password";
import { PlusForm } from "plus-pro-components";

defineOptions({
  name: "Me"
});

const { profileColumns, profileRules, profileValues, handleProfileUpdate } =
  useAdminProfile();
const { passwordColumns, passwordRules, passwordValues, handlePasswordUpdate } =
  useAdminPassword();
</script>

<template>
  <div class="flex flex-col gap-4">
    <el-card shadow="never" :style="{ border: 'none' }">
      <PlusForm
        v-model="profileValues"
        :columns="profileColumns"
        :rules="profileRules"
        :submit-text="'保存'"
        :reset-text="'重置'"
        :label-width="80"
        :label-position="'right'"
        :label-suffix="''"
        @submit="handleProfileUpdate"
      />
    </el-card>
    <el-card shadow="never" :style="{ border: 'none' }">
      <PlusForm
        v-model="passwordValues"
        :columns="passwordColumns"
        :rules="passwordRules"
        :submit-text="'保存'"
        :reset-text="'重置'"
        :label-width="80"
        :label-position="'right'"
        :label-suffix="''"
        @submit="handlePasswordUpdate"
      />
    </el-card>
  </div>
</template>
