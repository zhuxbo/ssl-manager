import { ref, onMounted, onUnmounted } from "vue";

/**
 * 响应式对话框宽度计算
 * 根据页面宽度自动调整对话框宽度
 * @returns 包含对话框宽度响应式引用的对象
 * @example
 * const { dialogSize } = useDialogSize();
 */
export function useDialogSize() {
  // 响应式对话框宽度
  const dialogSize = ref<string | number>(600);

  // 计算对话框宽度的函数
  const calculateDialogSize = () => {
    const windowWidth = window.innerWidth;

    if (windowWidth > 2000) {
      // 页面宽度大于2000px时，对话框宽度为页面的30%
      dialogSize.value = "30%";
    } else if (windowWidth >= 600) {
      // 页面宽度在600px-2000px之间时，对话框宽度为600px
      dialogSize.value = 600;
    } else {
      // 页面宽度小于600px时，对话框自适应宽度（使用百分比）
      dialogSize.value = "90%";
    }
  };

  // 窗口大小变化监听器
  const handleResize = () => {
    calculateDialogSize();
  };

  onMounted(() => {
    // 初始化对话框宽度
    calculateDialogSize();
    // 添加窗口大小变化监听
    window.addEventListener("resize", handleResize);
  });

  onUnmounted(() => {
    // 清理监听器
    window.removeEventListener("resize", handleResize);
  });

  return {
    dialogSize
  };
}
