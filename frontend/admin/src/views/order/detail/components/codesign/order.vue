<template>
  <el-card shadow="never" :style="{ border: 'none' }">
    <h2 class="title">
      <span>订单详情</span>
    </h2>
    <table class="descriptions">
      <tbody>
        <tr>
          <td class="label">用户</td>
          <td class="content">{{ order.user.username }}</td>
        </tr>
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
          <td class="content">
            {{ order.amount }}
            <el-button
              v-if="order.latest_cert?.status === 'unpaid'"
              style="padding: 0 5px 2px; margin: 0"
              type="primary"
              link
              size="small"
              @click="updateAmount"
            >
              点击修改
            </el-button>
          </td>
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
                : ""
            }}
          </td>
        </tr>
        <tr>
          <td class="label">有效期到</td>
          <td class="content">
            {{
              order.period_till
                ? dayjs(order.period_till).format("YYYY-MM-DD HH:mm:ss")
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
          <td class="label">会员备注</td>
          <td class="content">
            {{ order.remark }}
          </td>
        </tr>
        <tr>
          <td class="label">管理员备注</td>
          <td class="content">
            {{ order.admin_remark }}
            <el-button
              style="padding: 0 5px 2px; margin: 0"
              type="primary"
              link
              size="small"
              @click="remark"
            >
              {{ order.admin_remark ? "点击修改" : "点击添加" }}
            </el-button>
          </td>
        </tr>
      </tbody>
    </table>
  </el-card>
</template>
<script setup lang="ts">
import { inject, reactive } from "vue";
import { buildUUID } from "@pureadmin/utils";
import { ElMessageBox } from "element-plus";
import * as OrderApi from "@/api/order";
import { message } from "@shared/utils";
import { periodLabels } from "@/views/system/dictionary";
import dayjs from "dayjs";

const order = inject("order") as any;

const updateAmount = () => {
  ElMessageBox.prompt("请输入新的价格", "修改价格", {
    confirmButtonText: "确定",
    cancelButtonText: "取消",
    inputValue: String(order.latest_cert?.amount ?? order.amount),
    inputPattern: /^\d+(\.\d{1,2})?$/,
    inputErrorMessage: "请输入有效的金额"
  }).then(({ value }) => {
    OrderApi.updateAmount(order.id, value).then(() => {
      message("价格已更新", { type: "success" });
      OrderApi.show(order.id).then(res => {
        res.data.sync = buildUUID();
        Object.assign(order, reactive(res.data));
      });
    });
  });
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
