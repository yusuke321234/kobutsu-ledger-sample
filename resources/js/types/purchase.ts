// 仕入（Purchase＝受入）まわりの型（ID・区分コードは string、金額・数量は number）。

// 状態（sale の有無・is_draft から Service 側で導出）。
export type PurchaseStatus = 'in_stock' | 'sold' | 'draft'

// 在庫画面の状態フィルタ（すべて＝all）。
export type PurchaseStatusFilter = PurchaseStatus | 'all'

// 仕入の写真1枚。url は認証必須の配信ルート（/photos/{id}）。実体は Web 非公開なので直接は取れない。
export interface PurchasePhoto {
  id: string
  url: string
}

// 仕入に紐づく売却の要約。channel は区分コード string（SalesChannel）。
export interface SaleSummary {
  id: string
  sale_date: string | null
  channel: string
  sale_price: number
}

// 仕入1件（在庫の1行）。kubun / verification_method は区分コード string。
export interface Purchase {
  id: string
  transaction_date: string | null
  kubun: string
  item_name: string | null
  quantity: number
  features: string | null
  counterparty_name: string | null
  counterparty_address: string | null
  counterparty_occupation: string | null
  counterparty_age: string | null
  verification_method: string | null
  price: number | null
  is_exempt: boolean
  // 写真（1つの仕入につき5枚まで）
  photo_list: PurchasePhoto[]
  is_draft: boolean
  status: PurchaseStatus
  sale_data: SaleSummary | null
  // 単品の素朴粗利（売価−仕入）。売却がなければ null。
  raw_gross_profit: number | null
  created_at: string | null
}

// 相手方チップ1件。過去の仕入から DISTINCT で取得。
export interface Counterparty {
  counterparty_name: string
  counterparty_address: string | null
  counterparty_occupation: string | null
  counterparty_age: string | null
  verification_method: string | null
}
