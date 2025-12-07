import { ref } from "vue";

export function useOrderAction() {
  // 订单操作
  const action = ref({
    visible: false,
    type: "",
    id: 0
  });

  // 打开操作表单
  const openAction = (type = "apply", id = 0) => {
    action.value.id = id;
    action.value.type = type;
    action.value.visible = true;
  };

  return {
    action,
    openAction
  };
}
