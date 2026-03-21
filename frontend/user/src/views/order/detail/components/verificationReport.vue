<template>
  <div class="verification-report">
    <el-form label-width="140px" size="small">
      <div class="form-section">
        <div class="form-section-title">申请人信息</div>
        <el-form-item label="（中文）姓名">
          <el-input v-model="form.subscriber.chinese_transcription" />
        </el-form-item>
        <el-form-item label="（英文）姓名">
          <el-input v-model="form.subscriber.english_transcription" />
        </el-form-item>
        <el-form-item label="（中文）证件类型">
          <el-input v-model="form.subscriber.document_type_cn" />
        </el-form-item>
        <el-form-item label="（英文）证件类型">
          <el-input v-model="form.subscriber.document_type_en" />
        </el-form-item>
        <el-form-item label="证件号码">
          <el-input v-model="form.subscriber.document_number" />
        </el-form-item>
        <el-form-item label="出生日期">
          <el-date-picker
            v-model="form.subscriber.date_of_birth"
            type="date"
            format="YYYY-MM-DD"
            value-format="YYYY-MM-DD"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="证件有效期">
          <div class="date-with-check">
            <el-date-picker
              v-if="!docIndefinite"
              v-model="form.subscriber.document_expiry_date"
              type="date"
              format="YYYY-MM-DD"
              value-format="YYYY-MM-DD"
              style="width: 100%"
            />
            <el-checkbox
              v-model="docIndefinite"
              @change="onDocIndefiniteChange"
            >
              长期有效
            </el-checkbox>
          </div>
        </el-form-item>
      </div>

      <div class="form-section">
        <div class="form-section-title">企业信息</div>
        <el-form-item label="（中文）名称">
          <el-input v-model="form.organization.chinese_transcription" />
        </el-form-item>
        <el-form-item label="（英文）名称">
          <el-input v-model="form.organization.english_transcription" />
        </el-form-item>
        <el-form-item label="（中文）地址">
          <el-input v-model="form.organization.address_cn" />
        </el-form-item>
        <el-form-item label="（英文）地址">
          <el-input v-model="form.organization.address_en" />
        </el-form-item>
        <el-form-item label="企业信息确认链接">
          <div class="date-with-check">
            <el-input
              v-if="!linkNA"
              v-model="form.organization.confirmation_link"
            />
            <el-checkbox
              v-model="linkNA"
              @change="
                (v: boolean) => {
                  form.organization.confirmation_link = v ? 'N/A' : '';
                }
              "
              >不适用</el-checkbox
            >
          </div>
        </el-form-item>
        <el-form-item label="注册号">
          <div class="date-with-check">
            <el-input
              v-if="!regNA"
              v-model="form.organization.registration_number"
            />
            <el-checkbox
              v-model="regNA"
              @change="
                (v: boolean) => {
                  form.organization.registration_number = v ? 'N/A' : '';
                }
              "
              >不适用</el-checkbox
            >
          </div>
        </el-form-item>
      </div>

      <div class="form-section">
        <div class="form-section-title">授权信息</div>
        <el-form-item label="">
          <el-radio-group v-model="authMode" @change="handleAuthModeChange">
            <el-radio value="custom">自定义</el-radio>
            <el-radio value="legal">法定代表人</el-radio>
            <el-radio value="na">不适用</el-radio>
          </el-radio-group>
        </el-form-item>
        <template v-if="authMode === 'custom'">
          <el-form-item label="（英文）姓名">
            <el-input v-model="form.authorization.english_transcription" />
          </el-form-item>
          <el-form-item label="（中文）姓名">
            <el-input v-model="form.authorization.chinese_transcription" />
          </el-form-item>
          <el-form-item label="（英文）职位">
            <el-input v-model="form.authorization.position_en" />
          </el-form-item>
          <el-form-item label="（中文）职位">
            <el-input v-model="form.authorization.position_cn" />
          </el-form-item>
          <el-form-item label="授权日期">
            <el-date-picker
              v-model="form.authorization.date_of_issue"
              type="date"
              format="YYYY-MM-DD"
              value-format="YYYY-MM-DD"
              style="width: 100%"
            />
          </el-form-item>
          <el-form-item label="有效期">
            <div class="date-with-check">
              <el-date-picker
                v-if="!authIndefinite"
                v-model="form.authorization.expiration_date"
                type="date"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
                style="width: 100%"
              />
              <el-checkbox
                v-model="authIndefinite"
                @change="onAuthIndefiniteChange"
              >
                长期有效
              </el-checkbox>
            </div>
          </el-form-item>
        </template>
      </div>

      <el-form-item>
        <el-button type="primary" :loading="saving" @click="handleSave">
          保存
        </el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<script setup lang="ts">
import { ref, inject, onMounted } from "vue";
import { getVerificationReport, saveVerificationReport } from "@/api/order";
import { ElMessage } from "element-plus";

const order = inject("order") as any;

const authMode = ref("custom");
const docIndefinite = ref(false);
const linkNA = ref(false);
const regNA = ref(false);
const authIndefinite = ref(false);

const onDocIndefiniteChange = (val: boolean) => {
  form.value.subscriber.document_expiry_date = val ? "valid indefinitely" : "";
};

const onAuthIndefiniteChange = (val: boolean) => {
  form.value.authorization.expiration_date = val ? "valid indefinitely" : "";
};

const handleAuthModeChange = (mode: string) => {
  if (mode === "legal") {
    form.value.authorization = {
      auth_mode: "legal",
      english_transcription: form.value.subscriber.english_transcription,
      chinese_transcription: form.value.subscriber.chinese_transcription,
      position_en: "Legal Representative",
      position_cn: "法定代表人",
      date_of_issue: "N/A",
      expiration_date: "valid indefinitely"
    };
    authIndefinite.value = true;
  } else if (mode === "na") {
    form.value.authorization = {
      auth_mode: "na",
      english_transcription: "N/A",
      chinese_transcription: "N/A",
      position_en: "N/A",
      position_cn: "N/A",
      date_of_issue: "N/A",
      expiration_date: "N/A"
    };
    authIndefinite.value = false;
  } else {
    authIndefinite.value = false;
  }
};

const report = ref<any>(null);
const saving = ref(false);

const formatLocalDate = (date: Date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

const today = () => formatLocalDate(new Date());
const oneYearLater = () => {
  const d = new Date();
  d.setFullYear(d.getFullYear() + 1);
  return formatLocalDate(d);
};

const emptyForm = () => ({
  subscriber: {
    english_transcription: "",
    chinese_transcription: "",
    document_type_en: "ID Card",
    document_type_cn: "身份证",
    document_number: "",
    date_of_birth: "",
    document_expiry_date: ""
  },
  organization: {
    english_transcription: "",
    chinese_transcription: "",
    address_cn: "",
    address_en: "",
    confirmation_link: "",
    registration_number: ""
  },
  authorization: {
    auth_mode: "custom",
    english_transcription: "",
    chinese_transcription: "",
    position_en: "",
    position_cn: "",
    date_of_issue: today(),
    expiration_date: oneYearLater()
  }
});

const form = ref(emptyForm());

const prefillFromOrder = (prefill: any) => {
  if (prefill?.organization) {
    const org = prefill.organization;
    form.value.organization.chinese_transcription = org.name || "";
    form.value.organization.address_cn = org.address || "";
    form.value.organization.registration_number = org.registration_number || "";
  }
  if (prefill?.contact) {
    const contact = prefill.contact;
    form.value.subscriber.chinese_transcription =
      `${contact.last_name || ""} ${contact.first_name || ""}`.trim();
    if (contact.identification_number) {
      form.value.subscriber.document_number = contact.identification_number;
    }
  }
};

const loadReport = async () => {
  const res = await getVerificationReport(order.id);
  if (res.code === 1) {
    if (res.data?.report) {
      report.value = res.data.report;
      const saved = res.data.report.report_data || {};
      form.value = {
        subscriber: { ...emptyForm().subscriber, ...saved.subscriber },
        organization: { ...emptyForm().organization, ...saved.organization },
        authorization: { ...emptyForm().authorization, ...saved.authorization }
      };
      authMode.value = form.value.authorization.auth_mode || "custom";
      docIndefinite.value =
        form.value.subscriber.document_expiry_date === "valid indefinitely";
      linkNA.value = form.value.organization.confirmation_link === "N/A";
      regNA.value = form.value.organization.registration_number === "N/A";
      authIndefinite.value =
        form.value.authorization.expiration_date === "valid indefinitely";
    } else if (res.data?.prefill) {
      prefillFromOrder(res.data.prefill);
    }
  }
};

const handleSave = async () => {
  form.value.authorization.auth_mode = authMode.value;
  saving.value = true;
  try {
    const res = await saveVerificationReport(order.id, form.value);
    if (res.code === 1) {
      ElMessage.success("保存成功");
      await loadReport();
    }
  } finally {
    saving.value = false;
  }
};

onMounted(loadReport);
</script>

<style scoped lang="scss">
.verification-report {
  margin: 10px 0;
}

.form-section {
  margin-bottom: 15px;
}

.form-section-title {
  font-size: 13px;
  font-weight: 500;
  color: var(--el-text-color-regular);
  margin-bottom: 10px;
  padding-bottom: 5px;
  border-bottom: 1px solid var(--el-border-color-lighter);
}

.date-with-check {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
}
</style>
