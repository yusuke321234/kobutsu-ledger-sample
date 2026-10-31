import { createContext, use, useState, type ReactNode } from 'react'
import type { Purchase } from '@/types'

// 画面をまたいで開く共通モーダルの状態（仕入登録・売却）。
// 認証後のレイアウト（AppLayout）が持ち、ページ遷移しても保たれる。
// sessionKey はモーダルを開くたびに変わる番号で、フォームの中身を毎回初期化するために使う。

interface PurchaseFormState {
  open: boolean
  sessionKey: number
  // null＝新規登録、Purchase＝編集（インライン編集・下書きの完成）
  target: Purchase | null
}

interface SaleFormState {
  open: boolean
  sessionKey: number
  purchaseData: Purchase | null
}

export interface UiContextValue {
  purchaseForm: PurchaseFormState
  openPurchaseForm: (target?: Purchase | null) => void
  closePurchaseForm: () => void
  saleForm: SaleFormState
  openSaleForm: (purchaseData: Purchase) => void
  closeSaleForm: () => void
}

const UiContext = createContext<UiContextValue | null>(null)

export function UiProvider({ children }: { children: ReactNode }) {
  const [purchaseForm, setPurchaseForm] = useState<PurchaseFormState>({
    open: false,
    sessionKey: 0,
    target: null,
  })
  const [saleForm, setSaleForm] = useState<SaleFormState>({
    open: false,
    sessionKey: 0,
    purchaseData: null,
  })

  const value: UiContextValue = {
    purchaseForm,
    openPurchaseForm: (target = null) => {
      setPurchaseForm((current) => ({ open: true, sessionKey: current.sessionKey + 1, target }))
    },
    closePurchaseForm: () => {
      setPurchaseForm((current) => ({ ...current, open: false }))
    },
    saleForm,
    openSaleForm: (purchaseData) => {
      setSaleForm((current) => ({ open: true, sessionKey: current.sessionKey + 1, purchaseData }))
    },
    closeSaleForm: () => {
      setSaleForm((current) => ({ ...current, open: false }))
    },
  }

  return <UiContext value={value}>{children}</UiContext>
}

export function useUi(): UiContextValue {
  const value = use(UiContext)
  if (value !== null) {
    return value
  } else {
    throw new Error('useUi は AppLayout の中で使ってください。')
  }
}
