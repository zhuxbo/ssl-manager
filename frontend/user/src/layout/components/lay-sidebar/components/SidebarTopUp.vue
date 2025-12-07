<template>
  <el-button link @click="topUp.showDialog">
    余额 {{ useUserStoreHook().balance }}
  </el-button>
  <el-dialog
    v-model="topUp.visible"
    title="充值"
    :destroy-on-close="false"
    :close-on-click-modal="false"
    :width="dialogSize"
    append-to-body
  >
    <div class="dialog">
      <el-form :label-width="120" class="top-up-form" label-position="right">
        <el-form-item label="账户余额：">
          {{ useUserStoreHook().balance }}
        </el-form-item>
        <el-form-item
          v-show="payType == '' || payType == 'alipay' || payType == 'wechat'"
          label="充值金额："
          required
        >
          <el-input-number
            v-model="amount"
            :precision="0"
            :step="1"
            :min="1"
            controls-position="right"
            @change="handleChangeAmount"
          />
        </el-form-item>
        <el-form-item label="支付方式：">
          <el-radio-group v-model="payType">
            <el-radio-button value="alipay" @click="debounce(alipayScan, 300)()"
              >支付宝</el-radio-button
            >
            <el-radio-button value="wechat" @click="debounce(wechatScan, 300)()"
              >微信</el-radio-button
            >
            <el-radio-button value="transfer" @click="fundId = ''"
              >转账</el-radio-button
            >
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="payType == 'alipay'">
          <canvas ref="alipayPay" />
        </el-form-item>
        <el-form-item v-if="payType == 'wechat'">
          <canvas ref="wechatPay" />
        </el-form-item>
        <el-form-item v-if="payType == 'transfer'">
          <el-form>
            <br />
            <el-form-item label="户　名：">{{ bankAccount.name }}</el-form-item>
            <el-form-item label="账　号：">{{
              bankAccount.account
            }}</el-form-item>
            <el-form-item label="开户行：">{{ bankAccount.bank }}</el-form-item>
          </el-form>
        </el-form-item>
      </el-form>
    </div>
  </el-dialog>
</template>
<script setup lang="ts">
import { nextTick, ref, watch, onMounted } from "vue";
import { useUserStoreHook } from "@/store/modules/user";
import QRCode from "qrcode";
import { debounce } from "lodash-es";
import { alipay, wechat, check, getBankAccount } from "@/api/topUp";
import { topUpDialogStore } from "@/store/modules/topUp";
import { router } from "@/router";
import { useDialogSize } from "@/views/system/dialog";

// 使用统一的响应式对话框宽度
const { dialogSize } = useDialogSize();

// 充值对话框
const topUp = topUpDialogStore();

const amount = ref(1);
const payType = ref("");
const fundId = ref("");
const bankAccount = ref({
  name: "",
  account: "",
  bank: ""
});

onMounted(() => {
  getBankAccount().then(res => {
    bankAccount.value = res.data;
  });
});

const handleChangeAmount = () => {
  payType.value = "";
};

const alipayPay = ref<HTMLCanvasElement | null>(null);
const alipayScan = () => {
  alipay(amount.value.toString()).then(res => {
    fundId.value = res.data.fundId;
    QRCode.toCanvas(alipayPay.value, res.data.qr_code);
    nextTick();
    if (alipayPay.value) {
      alipayPay.value.style.width = "128px";
      alipayPay.value.style.height = "128px";
    }
  });
};

const wechatPay = ref<HTMLCanvasElement | null>(null);
const wechatScan = () => {
  wechat(amount.value.toString()).then(res => {
    fundId.value = res.data.fundId;
    QRCode.toCanvas(wechatPay.value, res.data.code_url);
    nextTick();
    if (wechatPay.value) {
      wechatPay.value.style.width = "128px";
      wechatPay.value.style.height = "128px";
    }
  });
};

type Timer = ReturnType<typeof setTimeout>;
let timer: Timer | undefined;

const checkPay = () => {
  check(fundId.value).then(res => {
    if (res?.data?.message == "successful") {
      // 清除定时器
      if (timer !== undefined) {
        clearInterval(timer as Timer);
      }
      topUp.hideDialog();
      payType.value = "";
      fundId.value = "";
      topUp.updateBalance();
      setTimeout(() => {
        router.push({
          name: "Funds",
          query: { time: new Date().getTime() }
        });
      }, 200);
    }
  });
};

watch(
  () => topUp.visible,
  newVal => {
    if (!newVal) {
      // 关闭对话框时，10秒钟后清除定时器
      setTimeout(() => {
        if (timer !== undefined) {
          clearInterval(timer as Timer);
        }
      }, 10000);
    } else {
      topUp.updateBalance();
      // 打开对话框时，如果 fundId 有值，设置定时器
      if (fundId.value) {
        if (timer !== undefined) {
          clearInterval(timer as Timer); // 确保在设置新定时器之前清除现有定时器
        }
        timer = setInterval(checkPay, 3000);
      }
    }
  }
);

watch(
  () => fundId.value,
  newVal => {
    if (newVal) {
      // 当fundId有有效值时，设置定时器
      if (timer !== undefined) {
        clearInterval(timer as Timer); // 确保在设置新定时器之前清除现有定时器
      }
      timer = setInterval(checkPay, 3000);
    } else {
      // 当fundId值为空，清除定时器
      if (timer !== undefined) {
        clearInterval(timer as Timer);
      }
    }
  }
);
</script>
<style scoped lang="scss">
.top-up-form :deep(.el-form-item__label) {
  font-weight: bold;
}

.dialog {
  height: 300px;
}
</style>
