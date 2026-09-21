import { ElMessageBox } from "element-plus";

export function cancellationMessage(order: any): string {
  const cert = order.latest_cert;
  if (cert?.status === "unpaid") {
    return "将立即删除当前未支付申请，不产生退款。续费或重签申请取消后恢复原证书记录。";
  }
  if (cert?.status === "pending") {
    return cert.action === "reissue"
      ? "将立即取消本次重签并恢复上一张证书，仅退回本次重签已支付的增量费用；零元重签不产生退款。"
      : "将立即取消当前申请，按订单规则退回已支付费用；续费申请取消后恢复原证书记录。";
  }
  return "确认后将立即提交取消处理，不再提供撤回。取消成功后按订单规则退款；已签发证书可能失效，请确认不再使用。";
}

/** 请求完成前保持弹窗，失败时保留输入，避免重复提交。 */
export async function confirmOrderAction(
  kind: "archive" | "cancel",
  description: string,
  submit: () => Promise<unknown>
): Promise<boolean> {
  const phrase = kind === "archive" ? "确认归档" : "确认取消";
  let submitting = false;
  try {
    await ElMessageBox.prompt(
      description,
      kind === "archive" ? "归档订单" : "取消订单",
      {
        inputPlaceholder: `请输入「${phrase}」`,
        inputValidator: value => value === phrase || `请输入「${phrase}」`,
        confirmButtonText: phrase,
        cancelButtonText: "返回",
        type: "warning",
        closeOnClickModal: true,
        beforeClose: async (action, instance, done) => {
          if (submitting) return;
          if (action !== "confirm") {
            done();
            return;
          }
          if (instance.inputValue !== phrase) return;
          submitting = true;
          instance.confirmButtonLoading = true;
          try {
            await submit();
            // 提交期间的关闭动作会改变 MessageBox action，成功后按确认结算。
            instance.action = "confirm";
            done();
          } catch {
            // HTTP 层显示业务错误，弹窗继续保留以便处理。
          } finally {
            submitting = false;
            instance.confirmButtonLoading = false;
          }
        }
      }
    );
    return true;
  } catch {
    return false;
  }
}

export const archiveMessage =
  "归档后，该订单将停止自动续费、自动重签和到期提醒，不能再通过此订单续费或重签。此操作不会退款或吊销已签发证书，历史记录保留，归档后不可恢复。";
