import type { Auth, Counterparty } from '@/types'

// Inertia の型設定（全画面共通の props・フラッシュ・レイアウトの props）。
// サーバー側は HandleInertiaRequests::share / FlashConsts と対応する。
declare module '@inertiajs/core' {
  export interface InertiaConfig {
    sharedPageProps: {
      auth: Auth
      // 相手方チップの履歴（在庫画面は常に、他の画面は「＋仕入」を開いた時だけ部分リロードで届く）
      counterpartyList?: Counterparty[]
      [key: string]: unknown
    }
    flashDataType: {
      // トーストに出す種類と文言（type で見た目が変わる）
      toastData?: { type: 'success' | 'error'; message: string }
    }
    layoutProps: {
      // 本文の最大幅（wide＝一覧系 1200px / normal＝読み物・フォーム系 950px）
      width: 'wide' | 'normal'
    }
  }
}
