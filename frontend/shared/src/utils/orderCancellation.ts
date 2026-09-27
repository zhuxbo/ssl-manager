/** 撤回仅对提交取消满一分钟、仍未完成的订单开放。 */
export function canRevokeOrderCancellation(
  order: any,
  now = Date.now()
): boolean {
  const cert = order.latest_cert;
  const requestedAt = cert?.cancel_requested_at || cert?.updated_at;
  return (
    cert?.status === "cancelling" &&
    !!requestedAt &&
    now - new Date(requestedAt).getTime() >= 60_000
  );
}

export function canBatchRevokeOrderCancellation(
  rows: any[],
  now = Date.now()
): boolean {
  const cancelling = rows.filter(
    row => row.latest_cert?.status === "cancelling"
  );
  return (
    cancelling.length > 0 &&
    cancelling.every(row => canRevokeOrderCancellation(row, now))
  );
}
