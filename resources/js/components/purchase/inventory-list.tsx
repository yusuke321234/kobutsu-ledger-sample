import { KUBUN_LABEL, PURCHASE_STATUS_LABEL, SALES_CHANNEL_LABEL } from '@/consts/purchase'
import { formatYen } from '@/lib/format'
import { cn } from '@/lib/utils'
import type { Purchase, PurchaseStatus } from '@/types'

interface InventoryListProps {
  purchaseList: Purchase[]
  onEdit: (purchaseData: Purchase) => void
  onRemove: (purchaseData: Purchase) => void
  onSell: (purchaseData: Purchase) => void
  onCancelSale: (purchaseData: Purchase) => void
}

// 在庫一覧。PC はテーブル、モバイルはカード。台帳の元データを一望する。
// 在庫中の行は「売れた」ワンタップで売却へ、売却済は粗利を見せて取り消しもできる。
export default function InventoryList({
  purchaseList,
  onEdit,
  onRemove,
  onSell,
  onCancelSale,
}: InventoryListProps) {
  return (
    <div>
      {/* PC：テーブル */}
      <table className="hidden w-full border-collapse text-sm md:table">
        <thead>
          <tr className="border-b border-gray-200 text-left text-xs text-gray-500">
            <th className="py-2 pr-3 font-medium">取引日</th>
            <th className="py-2 pr-3 font-medium">区分</th>
            <th className="py-2 pr-3 font-medium">品目</th>
            <th className="py-2 pr-3 font-medium">数量</th>
            <th className="py-2 pr-3 font-medium">相手方</th>
            <th className="py-2 pr-3 text-right font-medium">仕入額</th>
            <th className="py-2 pr-3 font-medium">状態</th>
            <th className="py-2 font-medium">操作</th>
          </tr>
        </thead>
        <tbody>
          {purchaseList.map((purchaseData) => (
            <tr
              key={purchaseData.id}
              className={cn(
                'border-b border-gray-100',
                purchaseData.is_draft === true && 'bg-amber-50/40',
              )}
            >
              <td className="py-2 pr-3 whitespace-nowrap text-gray-700">
                {purchaseData.transaction_date ?? '—'}
              </td>
              <td className="py-2 pr-3 whitespace-nowrap text-gray-600">
                {KUBUN_LABEL[purchaseData.kubun]}
              </td>
              <td className="py-2 pr-3 text-[#1b1b18]">{purchaseData.item_name ?? '（未入力）'}</td>
              <td className="py-2 pr-3 text-gray-700">{purchaseData.quantity}</td>
              <td className="py-2 pr-3 text-gray-700">{purchaseData.counterparty_name ?? '—'}</td>
              <td className="py-2 pr-3 text-right whitespace-nowrap text-gray-800">
                {formatYen(purchaseData.price)}
              </td>
              <td className="py-2 pr-3">
                <span
                  className={cn(
                    'rounded-full px-2 py-0.5 text-xs',
                    getStatusClass(purchaseData.status),
                  )}
                >
                  {PURCHASE_STATUS_LABEL[purchaseData.status]}
                </span>
                {/* 売却済は販路・売価・粗利をその場で見せる */}
                {purchaseData.sale_data !== null && (
                  <p className="mt-1 text-xs text-gray-500">{getSaleSummaryText(purchaseData)}</p>
                )}
                {purchaseData.raw_gross_profit !== null && (
                  <p className="text-xs text-gray-700">
                    粗利 {formatYen(purchaseData.raw_gross_profit)}
                  </p>
                )}
              </td>
              <td className="py-2 whitespace-nowrap">
                {/* 在庫中なら「売れた」がいちばん押される導線 */}
                {purchaseData.status === 'in_stock' && (
                  <button
                    type="button"
                    className="rounded-md bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-700"
                    onClick={() => onSell(purchaseData)}
                  >
                    売れた
                  </button>
                )}
                <button
                  type="button"
                  className="ml-2 text-sm text-[#1b1b18] underline hover:text-black"
                  onClick={() => onEdit(purchaseData)}
                >
                  {getEditLabel(purchaseData)}
                </button>
                {purchaseData.status === 'sold' && (
                  <button
                    type="button"
                    className="ml-2 text-sm text-gray-500 underline hover:text-gray-800"
                    onClick={() => onCancelSale(purchaseData)}
                  >
                    売却取消
                  </button>
                )}
                {/* 売却済みは削除できない（先に売却取消。台帳に払出しの行だけが残らないように） */}
                {purchaseData.status !== 'sold' && (
                  <button
                    type="button"
                    className="ml-3 text-sm text-gray-400 hover:text-[#f53003]"
                    onClick={() => onRemove(purchaseData)}
                  >
                    削除
                  </button>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      {/* モバイル：カード */}
      <ul className="space-y-3 md:hidden">
        {purchaseList.map((purchaseData) => (
          <li
            key={purchaseData.id}
            className={cn(
              'rounded-lg border border-gray-200 bg-white p-4',
              purchaseData.is_draft === true && 'border-amber-200 bg-amber-50/40',
            )}
          >
            <div className="flex items-start justify-between gap-2">
              <div className="flex min-w-0 items-start gap-2">
                {/* 1枚目の写真（現場で撮った品物がひと目で分かる） */}
                {purchaseData.photo_list.length > 0 && (
                  <img
                    src={purchaseData.photo_list[0].url}
                    alt=""
                    className="h-12 w-12 shrink-0 rounded-md border border-gray-200 object-cover"
                  />
                )}
                <p className="min-w-0 font-medium break-words text-[#1b1b18]">
                  {purchaseData.item_name ?? '（未入力の下書き）'}
                </p>
              </div>
              <span
                className={cn(
                  'shrink-0 rounded-full px-2 py-0.5 text-xs',
                  getStatusClass(purchaseData.status),
                )}
              >
                {PURCHASE_STATUS_LABEL[purchaseData.status]}
              </span>
            </div>
            <dl className="mt-2 space-y-1 text-sm text-gray-600">
              <div className="flex justify-between">
                <dt>取引日</dt>
                <dd>
                  {purchaseData.transaction_date ?? '—'}（{KUBUN_LABEL[purchaseData.kubun]}）
                </dd>
              </div>
              <div className="flex justify-between">
                <dt>相手方</dt>
                <dd>{purchaseData.counterparty_name ?? '—'}</dd>
              </div>
              <div className="flex justify-between">
                <dt>仕入額</dt>
                <dd className="text-gray-800">
                  {formatYen(purchaseData.price)}（×{purchaseData.quantity}）
                </dd>
              </div>
              {purchaseData.sale_data !== null && (
                <div className="flex justify-between">
                  <dt>売却</dt>
                  <dd>{getSaleSummaryText(purchaseData)}</dd>
                </div>
              )}
              {purchaseData.raw_gross_profit !== null && (
                <div className="flex justify-between">
                  <dt>粗利</dt>
                  <dd className="text-gray-800">{formatYen(purchaseData.raw_gross_profit)}</dd>
                </div>
              )}
            </dl>

            {/* 在庫中なら「売れた」を主導線として大きく出す（現場のワンタップ） */}
            {purchaseData.status === 'in_stock' && (
              <button
                type="button"
                className="mt-3 w-full rounded-md bg-emerald-600 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                onClick={() => onSell(purchaseData)}
              >
                売れた
              </button>
            )}

            <div className="mt-3 flex gap-4">
              <button
                type="button"
                className="text-sm text-[#1b1b18] underline hover:text-black"
                onClick={() => onEdit(purchaseData)}
              >
                {getEditLabel(purchaseData)}
              </button>
              {purchaseData.status === 'sold' && (
                <button
                  type="button"
                  className="text-sm text-gray-500 underline hover:text-gray-800"
                  onClick={() => onCancelSale(purchaseData)}
                >
                  売却取消
                </button>
              )}
              {purchaseData.status !== 'sold' && (
                <button
                  type="button"
                  className="text-sm text-gray-400 hover:text-[#f53003]"
                  onClick={() => onRemove(purchaseData)}
                >
                  削除
                </button>
              )}
            </div>
          </li>
        ))}
      </ul>
    </div>
  )
}

function getStatusClass(status: PurchaseStatus): string {
  if (status === 'in_stock') {
    return 'bg-emerald-50 text-emerald-700'
  } else if (status === 'sold') {
    return 'bg-gray-100 text-gray-600'
  } else {
    return 'bg-amber-50 text-amber-700'
  }
}

function getEditLabel(purchaseData: Purchase): string {
  if (purchaseData.is_draft === true) {
    return '完成させる'
  } else {
    return '編集'
  }
}

// 売却済の行に出す「販路・売価」の補足（売却がなければ null）。
function getSaleSummaryText(purchaseData: Purchase): string | null {
  const saleData = purchaseData.sale_data
  if (saleData !== null) {
    return `${SALES_CHANNEL_LABEL[saleData.channel]} ${formatYen(saleData.sale_price)}`
  } else {
    return null
  }
}
