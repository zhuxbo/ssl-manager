import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";

dayjs.extend(utc);

/**
 * 类型工具函数
 * 提供与 TypeScript 类型相关的实用工具函数
 */

/**
 * 使用预定义的键数组从对象中提取属性
 * @param obj 要提取属性的对象
 * @param keys 要提取的键数组
 * @returns 只包含指定键的新对象
 * @example
 * // 从 API 返回的数据中只提取 FormParams 类型定义的属性
 * const formData = pickByKeys<FormParams>(apiData, FORM_PARAMS_KEYS);
 */
export function pickByKeys<T>(
  obj: any,
  keys: readonly (string | number | symbol)[]
): T {
  const result = {} as T;

  for (const key of keys) {
    if (key in obj) {
      (result as any)[key] = obj[key];
    }
  }

  return result;
}

/**
 * 将日期范围转换为ISO格式，开始时间从00:00:00开始，结束时间到23:59:59结束
 * @param dates 日期范围数组 [开始日期, 结束日期]
 * @returns ISO格式的日期范围数组
 * @example
 * const dates = [new Date(), new Date()];
 * const isoDates = convertDateRangeToISO(dates);
 */
export function convertDateRangeToISO(
  dates: [Date | string | null, Date | string | null]
): [string, string] {
  if (!dates[0] || !dates[1]) return [null, null];

  return [
    dayjs.utc(dates[0]).startOf("day").toISOString(),
    dayjs.utc(dates[1]).endOf("day").toISOString()
  ];
}
