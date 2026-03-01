import { ref } from "vue";

interface DialogOptions {
  title?: string;
  fullscreen?: boolean;
  hideFooter?: boolean;
  contentRenderer?: () => any;
  props?: any;
  openDelay?: number;
  closeDelay?: number;
  closeCallBack?: (args: any) => void;
  visible?: boolean;
  [key: string]: any;
}

const dialogStore = ref<Array<DialogOptions>>([]);

/** 打开弹框 */
const addDialog = (options: DialogOptions) => {
  const open = () =>
    dialogStore.value.push(Object.assign(options, { visible: true }));
  if (options?.openDelay) {
    setTimeout(() => open(), options.openDelay);
  } else {
    open();
  }
};

/** 关闭弹框 */
const closeDialog = (options: DialogOptions, index: number, args?: any) => {
  dialogStore.value[index].visible = false;
  options.closeCallBack && options.closeCallBack({ options, index, args });

  const closeDelay = options?.closeDelay ?? 200;
  setTimeout(() => {
    dialogStore.value.splice(index, 1);
  }, closeDelay);
};

/** 关闭所有弹框 */
const closeAllDialog = () => {
  dialogStore.value = [];
};

export type { DialogOptions };
export { dialogStore, addDialog, closeDialog, closeAllDialog };
