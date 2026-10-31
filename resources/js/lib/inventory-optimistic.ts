import type { Purchase, PurchaseStatusFilter } from '@/types'

// 画面の props の一部として返すため、index signature を持てる type で定義する
type InventoryListProps = {
  purchaseList: Purchase[]
  totalCount: number
}

// 表示中の在庫一覧から1件を外した props を返す（削除・売却・売却取消を、返事を待たずに画面へ反映する）。
// 在庫画面以外にいる時や、外す必要のない状態フィルタの時は何もしない（undefined）。
export function excludePurchase(
  props: Record<string, unknown>,
  purchaseId: string,
  onlyWhenStatus?: PurchaseStatusFilter,
): InventoryListProps | undefined {
  const purchaseList = props.purchaseList
  const totalCount = props.totalCount
  const filterData = props.filterData as { status?: string } | undefined
  const currentStatus = filterData?.status

  // 在庫画面の props でなければ何もしない
  if (Array.isArray(purchaseList) && typeof totalCount === 'number') {
    // ok.
  } else {
    return undefined
  }

  // 状態の指定があれば、その状態を表示している時だけ外す
  if (onlyWhenStatus !== undefined) {
    if (currentStatus === onlyWhenStatus) {
      // ok.
    } else {
      return undefined
    }
  } else {
    // no action.
  }

  //
  return {
    purchaseList: (purchaseList as Purchase[]).filter((itemData) => itemData.id !== purchaseId),
    totalCount: totalCount - 1,
  }
}
