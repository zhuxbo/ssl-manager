<template>
  <UserInfoCard v-model="showUserInfoCard" :user-id="selectedUserId" />
</template>

<script setup lang="ts">
import { showUserInfoCard, selectedUserId } from "@/views/system/username";
import UserInfoCard from "@/components/UserInfoCard/index.vue";
</script>
