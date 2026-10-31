// 古物台帳（Ledger）の型（受入＋払出を時系列1表に。保存せず記録から都度生成）。

// 台帳の行種別。receive=受入（仕入）/ payout=払出（売却）。
export type LedgerRecordType = 'receive' | 'payout'

// 台帳の1行。様式15号の記載項目に対応する。
// kubun / verification_method は受入のみ、channel は払出のみ（他方は null）。
export interface LedgerRow {
  // 受入・払出が混在するため種別付きの行 id（一覧の key 用）。単体の参照は purchase_id
  id: string
  record_type: LedgerRecordType
  transaction_date: string | null
  kubun: string | null
  channel: string | null
  item_name: string | null
  quantity: number
  features: string | null
  counterparty_name: string | null
  counterparty_address: string | null
  counterparty_occupation: string | null
  counterparty_age: string | null
  verification_method: string | null
  // 受入＝仕入金額 / 払出＝売却金額
  amount: number | null
  purchase_id: string
}

// 台帳の絞り込み（URL のクエリと 1:1。未指定は null / 空文字）。
export interface LedgerFilters {
  from: string | null
  to: string | null
  q: string
}
