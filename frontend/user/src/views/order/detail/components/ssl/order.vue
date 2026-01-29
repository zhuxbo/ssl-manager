<template>
  <el-card shadow="never" :style="{ border: 'none' }">
    <h2 class="title">
      <span>订单详情</span>
    </h2>
    <table class="descriptions">
      <tbody>
        <tr>
          <td class="label">订单ID</td>
          <td class="content">{{ order.id }}</td>
        </tr>
        <tr>
          <td class="label">品牌</td>
          <td class="content">{{ order.brand }}</td>
        </tr>
        <tr>
          <td class="label">产品</td>
          <td class="content">{{ order.product.name }}</td>
        </tr>
        <tr>
          <td class="label">金额</td>
          <td class="content">{{ order.amount }}</td>
        </tr>
        <tr>
          <td class="label">购买时长</td>
          <td class="content">{{ periodLabels[order.period] }}</td>
        </tr>
        <tr>
          <td class="label">有效期从</td>
          <td class="content">
            {{
              order.period_from
                ? dayjs(order.period_from).format("YYYY-MM-DD HH:mm:ss")
                : "-"
            }}
          </td>
        </tr>
        <tr>
          <td class="label">有效期到</td>
          <td class="content">
            {{
              order.period_till
                ? dayjs(order.period_till).format("YYYY-MM-DD HH:mm:ss")
                : "-"
            }}
          </td>
        </tr>
        <tr>
          <td class="label">已购</td>
          <td class="content">
            {{
              order.purchased_standard_count
                ? order.purchased_standard_count + "个标准域名"
                : ""
            }}
            {{
              order.purchased_standard_count && order.purchased_wildcard_count
                ? "/"
                : ""
            }}
            {{
              order.purchased_wildcard_count
                ? order.purchased_wildcard_count + "个通配符"
                : ""
            }}
          </td>
        </tr>
        <tr v-if="order.organization">
          <td class="label">组织</td>
          <td class="content">
            {{ order.organization.name }},
            {{ order.organization.registration_number }}<br />
            {{ order.organization.phone }}, {{ order.organization.country }},
            {{ order.organization.state }}, {{ order.organization.city }},
            {{ order.organization.address }}, {{ order.organization.postcode
            }}<br />
          </td>
        </tr>
        <tr v-if="order.contact">
          <td class="label">联系人</td>
          <td class="content">
            {{ order.contact.last_name }}
            {{ order.contact.first_name }}, {{ order.contact.title }},
            {{ order.contact.phone }}, {{ order.contact.email }}<br />
          </td>
        </tr>
        <tr>
          <td class="label">备注</td>
          <td class="content">
            {{ order.remark }}
            <el-button
              style="padding: 0 5px 2px; margin: 0"
              type="primary"
              link
              size="small"
              @click="remark"
            >
              {{ order.remark ? "点击修改" : "点击添加" }}
            </el-button>
          </td>
        </tr>
        <tr>
          <td class="label">自动续费</td>
          <td class="content">
            <el-switch
              :model-value="order.auto_renew"
              :loading="autoLoading"
              @update:model-value="
                (val: boolean) => updateAutoSetting('auto_renew', val)
              "
            />
            <el-tooltip
              v-if="order.auto_renew === null || order.auto_renew === undefined"
              content="未设置，将使用您的全局设置"
              placement="top"
            >
              <el-icon style="margin-left: 5px; color: #909399"
                ><InfoFilled
              /></el-icon>
            </el-tooltip>
          </td>
        </tr>
        <tr>
          <td class="label">自动重签</td>
          <td class="content">
            <el-switch
              :model-value="order.auto_reissue"
              :loading="autoLoading"
              @update:model-value="
                (val: boolean) => updateAutoSetting('auto_reissue', val)
              "
            />
            <el-tooltip
              v-if="
                order.auto_reissue === null || order.auto_reissue === undefined
              "
              content="未设置，将使用您的全局设置"
              placement="top"
            >
              <el-icon style="margin-left: 5px; color: #909399"
                ><InfoFilled
              /></el-icon>
            </el-tooltip>
          </td>
        </tr>
      </tbody>
    </table>
  </el-card>
</template>
<script setup lang="ts">
import { inject, reactive, ref } from "vue";
import { buildUUID } from "@pureadmin/utils";
import { ElMessageBox } from "element-plus";
import { InfoFilled } from "@element-plus/icons-vue";
import * as OrderApi from "@/api/order";
import { message } from "@shared/utils";
import { periodLabels } from "@/views/system/dictionary";
import dayjs from "dayjs";

const order = inject("order") as any;

const autoLoading = ref(false);

const updateAutoSetting = async (
  key: "auto_renew" | "auto_reissue",
  value: boolean
) => {
  // 防止 el-switch 挂载时自动触发（null/undefined → false）
  // 只有用户明确操作才应该发送请求
  if (order[key] === value || (order[key] == null && value === false)) {
    return;
  }

  autoLoading.value = true;
  try {
    await OrderApi.updateAutoSettings(order.id, { [key]: value });
    // 请求成功后才更新 UI
    order[key] = value;
    message("设置已更新", { type: "success" });
  } catch (e) {
    // 请求失败，不更新值（保持原状态）
    message("更新失败", { type: "error" });
  } finally {
    autoLoading.value = false;
  }
};

const remark = () => {
  ElMessageBox.prompt("请填写备注，删除备注留空即可", "备注", {
    confirmButtonText: "确定",
    cancelButtonText: "取消"
  }).then(({ value }) => {
    OrderApi.remark(order.id, value ?? "").then(() => {
      message("备注已更新", { type: "success" });
      OrderApi.show(order.id).then(res => {
        res.data.sync = buildUUID();
        Object.assign(order, reactive(res.data));
      });
    });
  });
};
</script>
<style scoped lang="scss">
@import url("../../styles/detail.scss");
</style>
