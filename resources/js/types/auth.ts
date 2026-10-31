// 認証の型（共有 props の auth。HandleInertiaRequests::resolveAuth と 1:1）。

// ログインユーザー（共有 props の auth.user_data）。
export interface User {
  id: string
  family_name: string
  given_name: string
  email: string
  // 様式15号の帳票ヘッダに刷る営業所情報（未設定は null）
  office_name: string | null
  license_number: string | null
}

// 全画面に共有するログイン状態（HandleInertiaRequests::resolveAuth）。
export interface Auth {
  user_data: User | null
}
