import type { VNode } from "vue";
import { type MessageHandler, ElMessage } from "element-plus";

type messageTypes = "info" | "success" | "warning" | "error";

interface MessageParams {
  type?: messageTypes;
  plain?: boolean;
  icon?: any;
  dangerouslyUseHTMLString?: boolean;
  duration?: number;
  showClose?: boolean;
  offset?: number;
  grouping?: boolean;
  onClose?: Function | null;
}

export const message = (
  message: string | VNode | (() => VNode),
  params?: MessageParams
): MessageHandler => {
  if (!params) {
    return ElMessage({
      message,
      customClass: "pure-message"
    });
  }
  const {
    icon,
    type = "info",
    plain = false,
    dangerouslyUseHTMLString = false,
    duration = 2000,
    showClose = false,
    offset = 16,
    grouping = false,
    onClose
  } = params;

  return ElMessage({
    message,
    icon,
    type,
    plain,
    dangerouslyUseHTMLString,
    duration,
    showClose,
    offset,
    grouping,
    customClass: "pure-message",
    onClose: () => (typeof onClose === "function" ? onClose() : null)
  });
};
