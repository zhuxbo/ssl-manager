import { ref, onMounted, onUnmounted } from "vue";

/**
 * 响应式抽屉宽度计算
 * 根据页面宽度自动调整抽屉宽度
 * @returns 包含抽屉宽度响应式引用的对象
 * @example
 * const { drawerSize } = useDrawerSize();
 */
export function useDrawerSize() {
  // 响应式抽屉宽度
  const drawerSize = ref<string | number>(520);

  // 计算抽屉宽度的函数
  const calculateDrawerSize = () => {
    const windowWidth = window.innerWidth;

    if (windowWidth > 2080) {
      // 页面宽度大于2080px时，抽屉宽度为页面的25%
      drawerSize.value = "25%";
    } else if (windowWidth >= 520) {
      // 页面宽度在520px-2080px之间时，抽屉宽度为520px
      drawerSize.value = 520;
    } else {
      // 页面宽度小于520px时，抽屉自适应宽度（使用百分比）
      drawerSize.value = "90%";
    }
  };

  // 窗口大小变化监听器
  const handleResize = () => {
    calculateDrawerSize();
  };

  onMounted(() => {
    // 初始化抽屉宽度
    calculateDrawerSize();
    // 添加窗口大小变化监听
    window.addEventListener("resize", handleResize);
  });

  onUnmounted(() => {
    // 清理监听器
    window.removeEventListener("resize", handleResize);
  });

  return {
    drawerSize
  };
}
