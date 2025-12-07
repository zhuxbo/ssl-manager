import "plus-pro-components/es/components/search/style/css";
import type { PlusColumn } from "plus-pro-components";
import { debounce } from "lodash-es";
import { ref, computed } from "vue";
import { ArrowDown, ArrowUp } from "@element-plus/icons-vue";
import {
  brandOptions,
  productTypeOptions,
  encryptionStandardOptions,
  validationTypeOptions,
  nameTypeOptions,
  statusOptions
} from "@/views/system/dictionary";

// 创建一个通用的按钮组渲染函数
const createButtonGroupRenderer = (
  options: any[],
  config?: {
    showToggleButton?: boolean;
    onToggle?: () => void;
    collapsed?: boolean;
  }
) => {
  return (initialValue: any, onValueChange: (newValue: any) => void) => {
    // 使用ref跟踪当前选中的值
    const selectedValue = ref(initialValue);

    // 处理按钮点击
    const handleClick = (optionValue: string) => {
      // 如果点击的是当前选中的按钮，则取消选中
      if (selectedValue.value === optionValue) {
        selectedValue.value = undefined;
        onValueChange(undefined);
      } else {
        selectedValue.value = optionValue;
        onValueChange(optionValue);
      }
    };

    // 渲染为独立按钮组
    return (
      <div class="flex flex-wrap gap-1 items-center">
        <div class="flex flex-wrap gap-1">
          {options.map((item: any) => (
            <el-button
              key={item.value}
              type={selectedValue.value === item.value ? "primary" : "default"}
              onClick={() => handleClick(item.value)}
              class="ml-3 w-28"
            >
              {item.label}
            </el-button>
          ))}
        </div>
        {config?.showToggleButton && (
          <el-button
            type="primary"
            link
            onClick={config.onToggle}
            class="ml-3 flex items-center whitespace-nowrap text-base"
          >
            <el-icon class="mr-1">
              {config.collapsed ? <ArrowDown /> : <ArrowUp />}
            </el-icon>
            {config.collapsed ? "展开" : "收起"}
          </el-button>
        )}
      </div>
    );
  };
};

export const useProductSearch = (onSearch: () => void) => {
  const debouncedSearch = debounce(() => {
    onSearch();
  }, 500);

  // 添加折叠状态
  const collapsed = ref(true);

  // 重置窗口方法
  const onResize = () => {
    setTimeout(() => {
      window.dispatchEvent(new Event("resize"));
    }, 500);
  };

  const toggleCollapsed = () => {
    collapsed.value = !collapsed.value;
    onResize();
  };

  const searchColumns = computed((): PlusColumn[] => [
    {
      label: "证书品牌",
      prop: "brand",
      valueType: "radio",
      options: brandOptions,
      renderField: createButtonGroupRenderer(brandOptions, {
        showToggleButton: true,
        onToggle: toggleCollapsed,
        collapsed: collapsed.value
      })
    },
    {
      label: "产品类型",
      prop: "product_type",
      valueType: "radio",
      options: productTypeOptions,
      renderField: createButtonGroupRenderer(productTypeOptions),
      hideInSearch: collapsed.value
    },
    {
      label: "加密标准",
      prop: "encryption_standard",
      valueType: "radio",
      options: encryptionStandardOptions,
      renderField: createButtonGroupRenderer(encryptionStandardOptions),
      hideInSearch: collapsed.value
    },
    {
      label: "验证类型",
      prop: "validation_type",
      valueType: "radio",
      options: validationTypeOptions,
      renderField: createButtonGroupRenderer(validationTypeOptions),
      hideInSearch: collapsed.value
    },
    {
      label: "域名类型",
      prop: "name_type",
      valueType: "radio",
      options: nameTypeOptions,
      renderField: createButtonGroupRenderer(nameTypeOptions),
      hideInSearch: collapsed.value
    },
    {
      label: "产品状态",
      prop: "status",
      valueType: "radio",
      options: statusOptions,
      renderField: createButtonGroupRenderer(statusOptions),
      hideInSearch: collapsed.value
    },
    {
      label: "快速搜索",
      prop: "quickSearch",
      valueType: "input",
      fieldProps: {
        placeholder: "编码/名称/备注",
        clearable: true,
        style: {
          width: "240px"
        }
      },
      hideInSearch: collapsed.value
    }
  ]);

  return { searchColumns, debouncedSearch };
};
