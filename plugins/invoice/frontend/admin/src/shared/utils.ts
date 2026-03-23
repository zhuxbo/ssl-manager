import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";

dayjs.extend(utc);

/** 日期选择器快捷选项 */
export const getPickerShortcuts = (): Array<{
  text: string;
  value: Date | Function;
}> => {
  return [
    {
      text: "今天",
      value: () => {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const todayEnd = new Date();
        todayEnd.setHours(23, 59, 59, 999);
        return [today, todayEnd];
      }
    },
    {
      text: "昨天",
      value: () => {
        const yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);
        yesterday.setHours(0, 0, 0, 0);
        const yesterdayEnd = new Date();
        yesterdayEnd.setDate(yesterdayEnd.getDate() - 1);
        yesterdayEnd.setHours(23, 59, 59, 999);
        return [yesterday, yesterdayEnd];
      }
    },
    {
      text: "本周",
      value: () => {
        const today = new Date();
        const startOfWeek = new Date(
          today.getFullYear(),
          today.getMonth(),
          today.getDate() -
            today.getDay() +
            (today.getDay() === 0 ? -6 : 1)
        );
        startOfWeek.setHours(0, 0, 0, 0);
        const endOfWeek = new Date(
          startOfWeek.getTime() +
            6 * 24 * 60 * 60 * 1000 +
            23 * 60 * 60 * 1000 +
            59 * 60 * 1000 +
            59 * 1000 +
            999
        );
        return [startOfWeek, endOfWeek];
      }
    },
    {
      text: "本月",
      value: () => {
        const today = new Date();
        const startOfMonth = new Date(
          today.getFullYear(),
          today.getMonth(),
          1
        );
        startOfMonth.setHours(0, 0, 0, 0);
        const endOfMonth = new Date(
          today.getFullYear(),
          today.getMonth() + 1,
          0
        );
        endOfMonth.setHours(23, 59, 59, 999);
        return [startOfMonth, endOfMonth];
      }
    }
  ];
};

/** 将日期范围转换为 ISO 格式 */
export function convertDateRangeToISO(
  dates: [Date | string | null, Date | string | null]
): [string | null, string | null] {
  if (!dates[0] || !dates[1]) return [null, null];

  return [
    dayjs.utc(dates[0]).startOf("day").toISOString(),
    dayjs.utc(dates[1]).endOf("day").toISOString()
  ];
}

/** 响应式抽屉宽度 */
import { ref, onMounted, onUnmounted } from "vue";

export function useDrawerSize() {
  const drawerSize = ref<string | number>(520);

  const calculateDrawerSize = () => {
    const windowWidth = window.innerWidth;
    if (windowWidth > 2080) {
      drawerSize.value = "25%";
    } else if (windowWidth >= 520) {
      drawerSize.value = 520;
    } else {
      drawerSize.value = "90%";
    }
  };

  const handleResize = () => calculateDrawerSize();

  onMounted(() => {
    calculateDrawerSize();
    window.addEventListener("resize", handleResize);
  });

  onUnmounted(() => {
    window.removeEventListener("resize", handleResize);
  });

  return { drawerSize };
}

/** 从对象中按指定键提取子集 */
export function pickByKeys<T>(obj: Record<string, any>, keys: string[]): T {
  const result: Record<string, any> = {};
  for (const key of keys) {
    if (key in obj) {
      result[key] = obj[key];
    }
  }
  return result as T;
}
