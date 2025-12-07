import type { VNode } from "vue";
import { isFunction } from "@pureadmin/utils";
import { ElMessageBox } from "element-plus";

type messageBoxType = "alert" | "confirm" | "prompt";
type messageBoxTypes = "success" | "info" | "warning" | "error";

interface MessageBoxParams {
  /** 消息类型，可选 `alert` 、`confirm` 、`prompt` ，默认 `alert` */
  type?: messageBoxType;
  /** 图标类型，可选 `success` 、`info` 、`warning` 、`error` */
  iconType?: messageBoxTypes;
  /** 自定义图标组件 */
  icon?: string | VNode;
  /** 是否将 `message` 属性作为 `HTML` 片段处理，默认 `false` */
  dangerouslyUseHTMLString?: boolean;
  /** 是否显示取消按钮，默认 `false`（`confirm` 和 `prompt` 类型默认为 `true`） */
  showCancelButton?: boolean;
  /** 是否显示确定按钮，默认 `true` */
  showConfirmButton?: boolean;
  /** 取消按钮的文本内容，默认 `取消` */
  cancelButtonText?: string;
  /** 确认按钮的文本内容，默认 `确定` */
  confirmButtonText?: string;
  /** 取消按钮的自定义类名 */
  cancelButtonClass?: string;
  /** 确认按钮的自定义类名 */
  confirmButtonClass?: string;
  /** 是否在点击遮罩层时关闭 MessageBox，默认 `true` */
  closeOnClickModal?: boolean;
  /** 是否在按下 ESC 键时关闭 MessageBox，默认 `true` */
  closeOnPressEscape?: boolean;
  /** 是否在关闭 MessageBox 之前尝试聚焦到取消或确认按钮，默认 `true` */
  closeOnHashChange?: boolean;
  /** 是否显示右上角关闭按钮，默认 `true` */
  showClose?: boolean;
  /** 是否居中布局，默认 `false` */
  center?: boolean;
  /** 是否将标题和消息分开，默认 `false` */
  distinguishCancelAndClose?: boolean;
  /** 是否使用圆角按钮，默认 `false` */
  roundButton?: boolean;
  /** 自定义类名 */
  customClass?: string;
  /** 自定义内联样式 */
  customStyle?: Record<string, any>;
  /** 对话框外层容器的类名 */
  containerClass?: string;
  /** 弹窗宽度，可以是数字或百分比，默认为页面宽度的80%，最小400px，最大1600px */
  width?: string | number;
  /** 输入框的占位符（仅当 type 是 'prompt' 时有效） */
  inputPlaceholder?: string;
  /** 输入框的类型（仅当 type 是 'prompt' 时有效） */
  inputType?: string;
  /** 输入框的初始值（仅当 type 是 'prompt' 时有效） */
  inputValue?: string;
  /** 输入框的校验函数，返回布尔值或字符串（仅当 type 是 'prompt' 时有效） */
  inputValidator?: (value: string) => boolean | string;
  /** 输入框的校验提示信息（仅当 type 是 'prompt' 时有效） */
  inputErrorMessage?: string;
  /** 是否在页面加载时自动聚焦到输入框，默认 `true`（仅当 type 是 'prompt' 时有效） */
  inputAutoFocus?: boolean;
  /** 确认回调函数 */
  onConfirm?: (value?: string) => void;
  /** 取消回调函数 */
  onCancel?: () => void;
  /** 关闭回调函数 */
  onClose?: () => void;
}

/**
 * 将对象格式化为HTML字符串，使用缩进表示数据结构
 * @param obj 要格式化的对象
 */
function formatObjectToHTML(obj: any): string {
  if (obj === null) return "null";
  if (obj === undefined) return "undefined";
  if (typeof obj !== "object") return String(obj);

  let html = "";

  if (Array.isArray(obj)) {
    if (obj.length === 0) return "";
    return obj.map(item => formatObjectToHTML(item)).join("<br>");
  }

  const keys = Object.keys(obj);
  if (keys.length === 0) return "";

  keys.forEach(key => {
    const value = obj[key];
    if (typeof value === "object" && value !== null) {
      html += `${key}:<br>`;
      const formattedValue = formatObjectToHTML(value);
      html += formattedValue
        .split("<br>")
        .map(line => `&nbsp;&nbsp;${line}`)
        .join("<br>");
      html += "<br>";
    } else {
      html += `${key}: ${formatObjectToHTML(value)}<br>`;
    }
  });

  return html;
}

/**
 * `MessageBox` 消息弹框函数
 * @param title 标题
 * @param message 消息内容，如果是对象会被格式化为多行文本
 * @param params 其他参数
 */
const messageBox = (
  title: string,
  message: string | VNode | (() => VNode) | object,
  params?: MessageBoxParams
): Promise<any> => {
  // 如果 message 是对象，将其格式化为HTML字符串
  let formattedMessage: string | VNode | (() => VNode);

  if (
    typeof message === "object" &&
    message !== null &&
    !isFunction(message) &&
    !(message && typeof message === "object" && "component" in message)
  ) {
    formattedMessage = formatObjectToHTML(message);
  } else {
    formattedMessage = message as string | VNode | (() => VNode);
  }

  // 确保样式已添加
  addMessageBoxStyles();

  // 处理宽度设置
  const customClass = "message-box-custom-width";
  const width = params?.width;
  let customStyle: Record<string, any> = {};

  if (width) {
    const widthValue = typeof width === "number" ? `${width}px` : width;
    customStyle = {
      width: widthValue
    };
  }

  if (!params) {
    return ElMessageBox({
      title,
      message: formattedMessage,
      dangerouslyUseHTMLString: typeof formattedMessage === "string",
      showConfirmButton: false,
      showCancelButton: false,
      showClose: true,
      customClass,
      customStyle: customStyle
    });
  } else {
    const {
      type = "alert",
      dangerouslyUseHTMLString = typeof formattedMessage === "string",
      showConfirmButton = false,
      showCancelButton = false,
      showClose = true,
      onConfirm,
      onCancel,
      onClose,
      customClass: userCustomClass = "",
      customStyle: userCustomStyle = {},
      ...otherParams
    } = params;

    // 合并自定义样式
    const mergedCustomStyle = { ...customStyle, ...userCustomStyle };

    return ElMessageBox({
      title,
      message: formattedMessage,
      dangerouslyUseHTMLString,
      showConfirmButton,
      showCancelButton,
      showClose,
      customClass: `${customClass} ${userCustomClass}`.trim(),
      customStyle: mergedCustomStyle,
      ...otherParams,
      callback: (action, instance) => {
        if (action === "confirm" && onConfirm) {
          if (type === "prompt") {
            onConfirm((instance as any).inputValue);
          } else {
            onConfirm();
          }
        } else if (action === "cancel" && onCancel) {
          onCancel();
        } else if (action === "close" && onClose) {
          onClose();
        }
      }
    });
  }
};

/**
 * 添加MessageBox全局样式
 */
function addMessageBoxStyles() {
  const styleId = "message-box-global-styles";
  if (!document.getElementById(styleId)) {
    const style = document.createElement("style");
    style.id = styleId;
    style.textContent = `
      .el-message-box {
        max-width: 1200px !important;
        min-width: 400px !important;
      }
      .el-message-box.message-box-custom-width {
        width: 50% !important;
      }
      .el-message-box__message {
        word-break: break-word !important;
        white-space: normal !important;
      }
    `;
    document.head.appendChild(style);
  }
}

/**
 * `MessageBox.alert` 警告弹框函数
 * @param title 标题
 * @param message 消息内容，如果是对象会被格式化为多行文本
 * @param params 其他参数
 */
const alert = (
  title: string,
  message: string | VNode | (() => VNode) | object,
  params?: Omit<MessageBoxParams, "type">
): Promise<any> => {
  return messageBox(title, message, { ...params, type: "alert" });
};

/**
 * `MessageBox.confirm` 确认弹框函数
 * @param title 标题
 * @param message 消息内容，如果是对象会被格式化为多行文本
 * @param params 其他参数
 */
const confirm = (
  title: string,
  message: string | VNode | (() => VNode) | object,
  params?: Omit<MessageBoxParams, "type">
): Promise<any> => {
  return messageBox(title, message, {
    ...params,
    type: "confirm",
    showCancelButton: params?.showCancelButton !== false
  });
};

/**
 * `MessageBox.prompt` 输入弹框函数
 * @param title 标题
 * @param message 消息内容，如果是对象会被格式化为多行文本
 * @param params 其他参数
 */
const prompt = (
  title: string,
  message: string | VNode | (() => VNode) | object,
  params?: Omit<MessageBoxParams, "type">
): Promise<any> => {
  return messageBox(title, message, {
    ...params,
    type: "prompt",
    showCancelButton: params?.showCancelButton !== false
  });
};

export { messageBox, alert, confirm, prompt };
