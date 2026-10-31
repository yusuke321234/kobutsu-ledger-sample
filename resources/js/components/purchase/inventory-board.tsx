import { PlusIcon } from '@heroicons/react/20/solid'
import { router } from '@inertiajs/react'
import { useEffect, useEffectEvent, useState } from 'react'
import PurchaseController from '@/actions/App/Http/Controllers/Purchase/PurchaseController'
import SaleController from '@/actions/App/Http/Controllers/Sale/SaleController'
import MoreButton from '@/components/common/more-button'
import InventoryList from '@/components/purchase/inventory-list'
import QuickAddBar from '@/components/purchase/quick-add-bar'
import { STATUS_FILTER_LIST } from '@/consts/purchase'
import { useUi } from '@/hooks/use-ui'
import { excludePurchase } from '@/lib/inventory-optimistic'
import type { Counterparty, Purchase, PurchaseStatusFilter } from '@/types'

// 検索を打鍵ごとに送らないための待ち時間（ms）
const SEARCH_DEBOUNCE_MS = 300
// 状態フィルタの既定（サーバーの既定と同じ。URL には載せない）
const DEFAULT_STATUS: PurchaseStatusFilter = 'in_stock'

export interface InventoryBoardProps {
  filterData: { status: PurchaseStatusFilter; q: string }
  pageSize: number
  purchaseList: Purchase[]
  totalCount: number
  // 仕入が1件でもあるか（絞り込み・検索なし）
  hasPurchase: boolean
  counterpartyList: Counterparty[]
}

// 在庫ボード。在庫＝台帳の元データ。検索・状態フィルタ・PCクイック追加・一覧を束ねる。
// 絞り込みは URL のクエリ（?status=&q=）で持ち、サーバーから一覧を取り直す。
export default function InventoryBoard({
  filterData,
  pageSize,
  purchaseList,
  totalCount,
  hasPurchase,
  counterpartyList,
}: InventoryBoardProps) {
  const { openPurchaseForm, openSaleForm } = useUi()
  const [searchWord, setSearchWord] = useState(filterData.q)
  const [loadingMore, setLoadingMore] = useState(false)
  const [filtering, setFiltering] = useState(false)

  function visit(status: PurchaseStatusFilter, q: string): void {
    router.get(PurchaseController.getList.url(), getFilterQuery(status, q), {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['filterData', 'purchaseList', 'totalCount'],
      // 絞り込みを変えたら「もっと見る」で継ぎ足した分を捨てる
      reset: ['purchaseList'],
      onStart: () => setFiltering(true),
      onFinish: () => setFiltering(false),
    })
  }

  // 検索は打鍵ごとに送らず、入力が止まってから送る
  const search = useEffectEvent((q: string) => visit(filterData.status, q))
  useEffect(() => {
    const q = searchWord.trim()
    if (q !== filterData.q) {
      const timer = setTimeout(() => search(q), SEARCH_DEBOUNCE_MS)
      return () => clearTimeout(timer)
    } else {
      return undefined
    }
  }, [searchWord, filterData.q])

  // 「もっと見る」：次のページを取って一覧の後ろに継ぎ足す（URL は変えない）
  function loadMore(): void {
    const nextPage = Math.floor(purchaseList.length / pageSize) + 1
    router.reload({
      data: { current_page: nextPage },
      only: ['purchaseList', 'totalCount'],
      preserveUrl: true,
      onStart: () => setLoadingMore(true),
      onFinish: () => setLoadingMore(false),
    })
  }

  function deletePurchase(purchaseData: Purchase): void {
    const label = purchaseData.item_name ?? 'この仕入'
    const confirmed = window.confirm(
      `「${label}」を削除しますか？（在庫と台帳から消えます。記録はデータベースに保存されます）`,
    )
    if (confirmed === true) {
      router.delete(PurchaseController.delete.url(purchaseData.id), {
        preserveScroll: true,
        // 返事を待たずに一覧から外す（失敗したら元に戻る）
        optimistic: (props) => excludePurchase(props, purchaseData.id),
      })
    } else {
      // no action.
    }
  }

  // 売却の取り消し（間違えて「売れた」を押した時）。商品は在庫中に戻り、台帳の払出行も消える。
  function cancelSale(purchaseData: Purchase): void {
    const saleData = purchaseData.sale_data
    if (saleData !== null) {
      // ok.
    } else {
      return
    }

    const label = purchaseData.item_name ?? 'この商品'
    const confirmed = window.confirm(`「${label}」の売却を取り消しますか？（在庫中に戻ります）`)
    if (confirmed === true) {
      router.delete(SaleController.delete.url(saleData.id), {
        preserveScroll: true,
        // 「売却済」を表示中なら、返事を待たずに一覧から外す
        optimistic: (props) => excludePurchase(props, purchaseData.id, 'sold'),
      })
    } else {
      // no action.
    }
  }

  return (
    <div className="px-4 pb-16">
      {/* 検索 + 状態フィルタ */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <input
          type="search"
          placeholder="品目・特徴・相手方で検索"
          aria-label="検索"
          value={searchWord}
          onChange={(event) => setSearchWord(event.target.value)}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none sm:max-w-xs"
        />
        <div className="flex flex-wrap gap-2">
          {STATUS_FILTER_LIST.map((optionData) => (
            <button
              key={optionData.value}
              type="button"
              className={
                filterData.status === optionData.value
                  ? 'rounded-full bg-[#1b1b18] px-3 py-1 text-sm text-white'
                  : 'rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-600 hover:bg-gray-200'
              }
              onClick={() => visit(optionData.value as PurchaseStatusFilter, searchWord.trim())}
            >
              {optionData.label}
            </button>
          ))}
        </div>
      </div>

      <p className="mt-3 text-sm text-gray-500">{totalCount} 件</p>

      {/* PC：クイック追加（引き継ぎコンテキスト + 追加行） */}
      <div className="mt-3 hidden md:block">
        <QuickAddBar counterpartyList={counterpartyList} />
      </div>

      {/* モバイル：仕入登録ボタン（本体フォームは全画面共通モーダル） */}
      <button
        type="button"
        className="mt-3 flex w-full items-center justify-center gap-1 rounded-md bg-[#1b1b18] px-4 py-2.5 text-sm font-semibold text-white hover:bg-black md:hidden"
        onClick={() => openPurchaseForm(null)}
      >
        <PlusIcon className="h-4 w-4" aria-hidden="true" />
        <span>仕入を登録</span>
      </button>

      {/* 一覧 */}
      <div className="mt-6">
        {purchaseList.length > 0 ? (
          <InventoryList
            purchaseList={purchaseList}
            onEdit={(purchaseData) => openPurchaseForm(purchaseData)}
            onRemove={deletePurchase}
            onSell={openSaleForm}
            onCancelSale={cancelSale}
          />
        ) : (
          filtering === false && (
            <div className="py-16 text-center text-gray-500">
              {/* 仕入はあるのに0件なら、絞り込み・検索に当てはまらないだけと伝える */}
              {hasPurchase === true ? (
                <>
                  <p className="text-sm">この条件に当てはまる仕入はありません。</p>
                  <p className="mt-1 text-xs">
                    絞り込み（在庫中・売却済・下書き・すべて）や検索語を変えてみてください。
                  </p>
                </>
              ) : (
                <>
                  <p className="text-sm">まだ登録がありません。</p>
                  <p className="mt-1 text-xs">
                    仕入を記録すると、法定の古物台帳が同時にできあがります。
                  </p>
                </>
              )}
            </div>
          )
        )}

        <MoreButton
          loading={loadingMore === true}
          hasMore={purchaseList.length < totalCount}
          onLoad={loadMore}
        />
      </div>
    </div>
  )
}

// URL のクエリ（既定値は載せない）
function getFilterQuery(status: PurchaseStatusFilter, q: string): Record<string, string> {
  const query: Record<string, string> = {}
  if (status !== DEFAULT_STATUS) {
    query.status = status
  } else {
    // no action.
  }
  if (q !== '') {
    query.q = q
  } else {
    // no action.
  }
  return query
}
