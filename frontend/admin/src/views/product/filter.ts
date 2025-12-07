/**
 * 表单字段过滤工具
 * 用于过滤表单中不在选项列表中的值
 * 并按 options 的顺序排序
 */

/**
 * 过滤 checkbox 字段值，只保留在 options 中的值，并按 options 的顺序排序
 * @param values - 要过滤的值数组
 * @param options - 选项数组
 * @returns 过滤后的值数组（按 options 的顺序排序）
 */
export function filterCheckboxValues<T extends string | number>(
  values: T[] | undefined,
  options: Array<{ label: string; value: T }>
): T[] | undefined {
  if (!values || !Array.isArray(values)) {
    return values;
  }
  const validValues = options.map(opt => opt.value);
  // 过滤并按照 options 的顺序排序
  return values
    .filter(val => validValues.includes(val))
    .sort((a, b) => {
      const indexA = validValues.indexOf(a);
      const indexB = validValues.indexOf(b);
      return indexA - indexB;
    });
}

/**
 * 字段过滤配置类型
 */
export interface FieldFilterConfig<T extends string | number> {
  field: string;
  options: Array<{ label: string; value: T }>;
}

/**
 * 根据配置过滤表单字段值
 * @param values - 表单值对象
 * @param configs - 字段过滤配置数组
 * @returns 过滤后的表单值对象
 */
export function filterFormFieldsByConfig<T extends Record<string, any>>(
  values: T,
  configs: FieldFilterConfig<any>[]
): T {
  const filtered = { ...values } as Record<string, any>;

  configs.forEach(config => {
    const fieldValue = filtered[config.field];
    if (fieldValue) {
      filtered[config.field] = filterCheckboxValues(fieldValue, config.options);
    }
  });

  return filtered as T;
}
