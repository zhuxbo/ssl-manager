import { ref } from "vue";
import dayjs from "dayjs";
import { payMethodOptions, rechargedOptions } from "./dictionary";
import type { AgisoDetail } from "../../api/agiso";

export const useAgisoDetail = () => {
  const showDrawer = ref(false);
  const detailData = ref<AgisoDetail>({} as AgisoDetail);
  const loading = ref(false);

  const dataList = ref([
    { title: "原始数据", name: "data", data: null as any }
  ]);

  const openDrawer = (data: any) => {
    detailData.value = data;
    showDrawer.value = true;
    dataList.value = [
      { title: "原始数据", name: "data", data: detailData.value.data }
    ];
  };

  const closeDrawer = () => {
    showDrawer.value = false;
    detailData.value = {} as AgisoDetail;
    dataList.value = [{ title: "原始数据", name: "data", data: null }];
  };

  const columns = [
    { label: "ID", prop: "id" },
    {
      label: "用户名",
      prop: "user.username",
      cellRenderer: () => detailData.value.user?.username || "无关联用户"
    },
    {
      label: "用户邮箱",
      prop: "user.email",
      cellRenderer: () => detailData.value.user?.email || "-"
    },
    {
      label: "支付方式",
      prop: "pay_method",
      cellRenderer: () => {
        const pm = detailData.value.pay_method;
        return payMethodOptions.find(item => item.value === pm)?.label || pm;
      }
    },
    { label: "交易单号", prop: "tid" },
    { label: "类型", prop: "type" },
    {
      label: "充值状态",
      prop: "recharged",
      cellRenderer: () => {
        const recharged = detailData.value.recharged;
        return (
          rechargedOptions.find(item => item.value === recharged)?.label ||
          recharged
        );
      }
    },
    { label: "产品代码", prop: "product_code" },
    { label: "周期", prop: "period" },
    { label: "数量", prop: "count" },
    { label: "单价", prop: "price" },
    { label: "金额", prop: "amount" },
    {
      label: "订单ID",
      prop: "order_id",
      cellRenderer: () => detailData.value.order_id || "无关联订单"
    },
    {
      label: "创建时间",
      prop: "created_at",
      cellRenderer: () =>
        detailData.value.created_at
          ? dayjs(detailData.value.created_at).format("YYYY-MM-DD HH:mm:ss")
          : "-"
    }
  ];

  return {
    showDrawer,
    detailData,
    loading,
    columns,
    dataList,
    openDrawer,
    closeDrawer
  };
};
