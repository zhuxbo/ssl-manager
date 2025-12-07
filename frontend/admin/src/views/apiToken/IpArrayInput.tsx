import { ref, defineComponent, type PropType } from "vue";
import { Delete } from "@element-plus/icons-vue";
import isIP from "validator/lib/isIP";

export default defineComponent({
  name: "IpArrayInput",
  props: {
    modelValue: {
      type: Array as PropType<string[]>,
      default: () => []
    },
    maxItems: {
      type: Number,
      default: 10
    }
  },
  emits: ["update:modelValue"],
  setup(props, { emit }) {
    const ipErrors = ref<string[]>(Array(props.modelValue.length).fill(""));

    // IP验证函数
    const validateIP = (ip: string, index: number) => {
      if (!ip) {
        ipErrors.value[index] = "IP地址不能为空";
        return false;
      }
      if (!isIP(ip, 4) && !isIP(ip, 6)) {
        ipErrors.value[index] = "请输入有效的IP地址";
        return false;
      }
      ipErrors.value[index] = "";
      return true;
    };

    // 添加IP
    const addItem = () => {
      const newArray = [...props.modelValue, ""];
      ipErrors.value.push("");
      emit("update:modelValue", newArray);
    };

    // 删除IP
    const removeItem = (index: number) => {
      const newArray = [...props.modelValue];
      newArray.splice(index, 1);
      ipErrors.value.splice(index, 1);
      emit("update:modelValue", newArray);
    };

    // 更新IP，在blur时验证
    const updateItem = (index: number, newValue: string) => {
      const newArray = [...props.modelValue];
      newArray[index] = newValue;
      emit("update:modelValue", newArray);
    };

    // 在失去焦点时验证
    const validateOnBlur = (index: number) => {
      validateIP(props.modelValue[index], index);
    };

    // 对外提供的验证方法
    const validate = (): { valid: boolean; message?: string } => {
      // if (props.modelValue.length === 0) {
      //   return { valid: false, message: "请输入IP白名单" };
      // }

      if (props.modelValue.length > props.maxItems) {
        return { valid: false, message: `IP白名单不能超过${props.maxItems}个` };
      }

      for (let i = 0; i < props.modelValue.length; i++) {
        const isValid = validateIP(props.modelValue[i], i);
        if (!isValid) {
          return { valid: false, message: ipErrors.value[i] };
        }
      }

      return { valid: true };
    };

    return {
      ipErrors,
      addItem,
      removeItem,
      updateItem,
      validateOnBlur,
      validate
    };
  },
  render() {
    return (
      <div>
        {this.modelValue.map((ip, index) => (
          <div class="flex flex-col mb-2" key={index}>
            <div class="flex items-center">
              <el-input
                modelValue={ip}
                placeholder={`IP地址 ${index + 1}`}
                onUpdate:modelValue={(val: string) =>
                  this.updateItem(index, val)
                }
                onBlur={() => this.validateOnBlur(index)}
                status={this.ipErrors[index] ? "error" : ""}
              />
              <el-button
                type="danger"
                size="small"
                plain
                class="ml-2"
                onClick={() => this.removeItem(index)}
              >
                <el-icon>
                  <Delete />
                </el-icon>
              </el-button>
            </div>
            {this.ipErrors[index] && (
              <div class="text-red-500 text-xs mt-1">
                {this.ipErrors[index]}
              </div>
            )}
          </div>
        ))}
        <el-button
          type="primary"
          size="small"
          plain
          class="mt-2"
          onClick={this.addItem}
        >
          添加IP
        </el-button>
        {this.modelValue.length > this.maxItems && (
          <div class="text-red-500 text-xs mt-1">
            IP白名单不能超过{this.maxItems}个
          </div>
        )}
      </div>
    );
  }
});
