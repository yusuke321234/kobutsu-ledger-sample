// 古物台帳まわりの固定値。

// 行種別の表示名（受入＝仕入 / 払出＝売却）。
export const RECORD_TYPE_LABEL: Record<string, string> = {
  receive: '受入',
  payout: '払出',
}

// 期間プリセットの種類。custom は from/to を直接指定する。
export type PeriodPresetValue = 'all' | 'this_year' | 'last_year' | 'this_month' | 'custom'

export const PERIOD_PRESET_LIST: { value: PeriodPresetValue; label: string }[] = [
  { value: 'all', label: 'すべて' },
  { value: 'this_year', label: '今年' },
  { value: 'last_year', label: '去年' },
  { value: 'this_month', label: '今月' },
]

// プリセットから期間（from/to）を求める。all は期間指定なし（null）。
// 確定申告は暦年（1/1〜12/31）が単位なので、今年/去年は暦年で切る。
export function resolvePeriod(preset: PeriodPresetValue): {
  from: string | null
  to: string | null
} {
  const now = new Date()
  const year = now.getFullYear()

  if (preset === 'this_year') {
    return { from: `${year}-01-01`, to: `${year}-12-31` }
  } else if (preset === 'last_year') {
    return { from: `${year - 1}-01-01`, to: `${year - 1}-12-31` }
  } else if (preset === 'this_month') {
    const month = String(now.getMonth() + 1).padStart(2, '0')
    const lastDay = new Date(year, now.getMonth() + 1, 0).getDate()
    return {
      from: `${year}-${month}-01`,
      to: `${year}-${month}-${String(lastDay).padStart(2, '0')}`,
    }
  } else {
    // all / custom は呼び出し側が持つ値をそのまま使う
    return { from: null, to: null }
  }
}
