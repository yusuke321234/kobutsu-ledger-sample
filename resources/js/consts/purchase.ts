// 仕入まわりの固定値（バックエンドの Kubun / VerificationMethod / SalesChannel Enum と一致）。
// API へ送る/受け取るのは string の区分コード（02-naming-conventions）。

// ----- 区分（Kubun）-----
// 受入れ（仕入）の区別は法令上「買受け／委託」の2つ（別記様式第15号 備考1）。
// 払出しの区別（売却／委託に基づく引渡し）は台帳生成時にバックエンドで付ける。
export const KUBUN = {
  BUY: '1',
  CONSIGN: '2',
} as const

export const KUBUN_OPTION_LIST: { value: string; label: string }[] = [
  { value: '1', label: '買受け' },
  { value: '2', label: '委託' },
]

export const KUBUN_LABEL: Record<string, string> = {
  '1': '買受け',
  '2': '委託',
}

// ----- 確認方法（VerificationMethod）-----
export const VERIFICATION_METHOD = {
  LICENSE: '1',
  MYNUMBER_CARD: '2',
  PASSPORT: '3',
  DEALER: '4',
  REMOTE_FLEA: '5',
  OTHER: '8',
  EXEMPT: '9',
} as const

export const VERIFICATION_METHOD_OPTION_LIST: { value: string; label: string }[] = [
  { value: '1', label: '運転免許証の提示' },
  { value: '2', label: 'マイナンバーカードの提示' },
  { value: '3', label: 'パスポートの提示' },
  { value: '4', label: '古物商・店舗からの仕入' },
  { value: '5', label: '非対面取引（法定の非対面確認方法）' },
  { value: '8', label: 'その他の確認方法' },
  { value: '9', label: '確認免除（1万円未満等）' },
]

export const VERIFICATION_METHOD_LABEL: Record<string, string> = {
  '1': '運転免許証の提示',
  '2': 'マイナンバーカードの提示',
  '3': 'パスポートの提示',
  '4': '古物商・店舗からの仕入',
  '5': '非対面取引',
  '8': 'その他',
  '9': '確認免除',
}

// ----- 販路（SalesChannel）。売却の登録・一覧の売却済表示に使う。-----
export const SALES_CHANNEL = {
  MERCARI: '1',
  YAHOO_AUCTION: '2',
  YAHOO_FLEA: '3',
  AMAZON: '4',
  RAKUMA: '5',
  IN_PERSON: '6',
  OTHER: '9',
} as const

export const SALES_CHANNEL_LABEL: Record<string, string> = {
  '1': 'メルカリ',
  '2': 'ヤフオク',
  '3': 'Yahoo!フリマ',
  '4': 'Amazon',
  '5': '楽天ラクマ',
  '6': '店頭・対面',
  '9': 'その他',
}

export const SALES_CHANNEL_OPTION_LIST: { value: string; label: string }[] = [
  { value: '1', label: 'メルカリ' },
  { value: '2', label: 'ヤフオク' },
  { value: '3', label: 'Yahoo!フリマ' },
  { value: '4', label: 'Amazon' },
  { value: '5', label: '楽天ラクマ' },
  { value: '6', label: '店頭・対面' },
  { value: '9', label: 'その他' },
]

// ----- 状態（API 側で sale の有無・is_draft から導出）-----
export const PURCHASE_STATUS_LABEL: Record<string, string> = {
  in_stock: '在庫中',
  sold: '売却済',
  draft: '下書き',
}

// 在庫画面の状態フィルタタブ。
export const STATUS_FILTER_LIST: { value: string; label: string }[] = [
  { value: 'in_stock', label: '在庫中' },
  { value: 'sold', label: '売却済' },
  { value: 'draft', label: '下書き' },
  { value: 'all', label: 'すべて' },
]

// 記帳免除の目安（1万円未満）。この額を下回る買受は確認・記帳が任意（例外品目を除く）。
export const EXEMPTION_THRESHOLD = 10000

// 例外品目＝対価が1万円未満でも確認・記帳が義務となる古物（古物営業法施行規則 第16条）。
// 免除チェック時に必ず全リストを提示し（＝法定の正）、item_name のキーワードで補助的に自動フラグする。
// keywords は判定の網羅ではなく気づきの補助（自由入力なので取りこぼしは前提。最終判断は古物商の責任）。
// ※2025/10（令和7年10月1日）改正で金属4品目を追加。
export interface ExceptionItemCategory {
  label: string
  note?: string
  keywords: string[]
}

export const EXCEPTION_ITEM_LIST: ExceptionItemCategory[] = [
  {
    // 施行規則16条2項一号は「自動二輪車及び原動機付自転車」。自動車（四輪）は例外品目に含まれない
    label: '自動二輪車・原付とその部品',
    note: 'ねじ・ボルト等の汎用部品は対象外',
    keywords: ['バイク', '原付', '原動機', '自動二輪', 'スクーター', 'カウル'],
  },
  {
    label: 'ゲームソフト',
    keywords: [
      'ゲーム',
      'ソフト',
      'switch',
      'スイッチ',
      'ニンテンドー',
      'nintendo',
      'ps4',
      'ps5',
      'プレステ',
      'wii',
      '3ds',
    ],
  },
  {
    label: 'CD・DVD・ブルーレイ等（光学ディスク）',
    keywords: ['cd', 'dvd', 'ブルーレイ', 'blu-ray', 'bd', 'ディスク'],
  },
  {
    label: '書籍',
    keywords: ['書籍', '雑誌', 'コミック', '漫画', 'マンガ', '文庫', '写真集', '参考書', '図鑑'],
  },
  {
    label: 'エアコン室外機・電気温水器のヒートポンプ・電線・金属製グレーチング（2025年10月〜）',
    note: '家庭用の延長・充電・LANケーブル、コンクリ/FRP製グレーチングは対象外',
    keywords: [
      'エアコン',
      '室外機',
      '室外ユニット',
      '温水器',
      'ヒートポンプ',
      '電線',
      '銅線',
      'グレーチング',
    ],
  },
]

// 品名（item_name）から該当しそうな例外品目カテゴリを1つ返す（見つからなければ null）。免除チェックの補助警告用。
export function matchExceptionCategory(itemName: string | null): ExceptionItemCategory | null {
  if (itemName === null) {
    return null
  }

  const normalized = itemName.trim().toLowerCase()
  if (normalized === '') {
    return null
  }

  const matched = EXCEPTION_ITEM_LIST.find((category) =>
    category.keywords.some((keyword) => normalized.includes(keyword.toLowerCase())),
  )
  return matched ?? null
}
