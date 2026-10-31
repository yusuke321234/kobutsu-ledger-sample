// 表示・入力の整形（複数の画面で共用する小さな関数）。

// 金額表示（¥12,000）。null は「—」。
export function formatYen(value: number | null): string {
  if (value !== null) {
    return `¥${value.toLocaleString()}`
  } else {
    return '—'
  }
}

// 今日の日付（YYYY-MM-DD。取引日・売却日の初期値）。
export function getTodayString(): string {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

// 数値入力欄の文字列を number に直す（空・不正は null）。
export function toNumberOrNull(value: string): number | null {
  const text = value.trim()
  if (text !== '') {
    // 数字として読めるときだけ number にする
    const number = Number(text)
    if (Number.isNaN(number) === false) {
      return number
    } else {
      return null
    }
  } else {
    return null
  }
}

// number を数値入力欄の文字列に直す（null は空欄）。
export function toInputText(value: number | null): string {
  if (value !== null) {
    return String(value)
  } else {
    return ''
  }
}

// 数量入力欄の文字列を 1 以上の整数に直す（空・不正は 1）。
export function toQuantity(value: string): number {
  const quantity = toNumberOrNull(value)
  if (quantity !== null && quantity >= 1) {
    return Math.floor(quantity)
  } else {
    return 1
  }
}

// 任意入力の文字列を前後の空白を除いて返す（空は null）。
export function toTextOrNull(value: string): string | null {
  const text = value.trim()
  if (text !== '') {
    return text
  } else {
    return null
  }
}

// 選択欄の値を返す（未選択の '' は null）。
export function toCodeOrNull(value: string): string | null {
  if (value !== '') {
    return value
  } else {
    return null
  }
}
