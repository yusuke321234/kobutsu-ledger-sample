import { createInertiaApp } from '@inertiajs/react'
import AppToaster from '@/components/common/app-toaster'
import AppLayout from '@/layouts/app-layout'
import GuestLayout from '@/layouts/guest-layout'
import type { Auth, User } from '@/types'

// 画面（React + Inertia）のエントリポイント。ページは resources/js/pages/ から自動で読み込む。
void createInertiaApp({
  //
  title: getDocumentTitle,

  // 画面ごとのレイアウト（name は Inertia::render() に渡した画面名）
  layout: (name, page) => {
    //
    const pageGroup = getPageGroup(name)

    //
    if (pageGroup === 'auth') {
      // ログイン・登録・パスワード再設定は、ヘッダーなし
      return GuestLayout
    } else if (pageGroup === 'error') {
      // エラー画面は、ログイン中ならヘッダー付き、未ログインならヘッダーなし（ヘッダーのメニューはログインしないと使えない）
      const user = getLoggedInUser(page.props)

      //
      if (user !== null) {
        return AppLayout
      } else {
        return GuestLayout
      }
    } else {
      // それ以外（ログイン後の画面）は、ヘッダー付き
      return AppLayout
    }
  },

  //
  strictMode: true,

  // トーストの置き場は全画面で1つ。ページより先に置き、初回表示のフラッシュも受けられるようにする
  withApp(app) {
    return (
      <>
        <AppToaster />
        {app}
      </>
    )
  },

  //
  progress: {
    color: '#1b1b18',
  },
})

// ログイン中のユーザーを返す（未ログインなら null）。
// auth はサーバーの HandleInertiaRequests が全画面に付けるが、それより前に起きたエラー
//（メンテナンス中の 503 など）の画面には付かないため、auth が無ければ未ログインとして扱う
function getLoggedInUser(pageProps: object): User | null {
  const auth = (pageProps as { auth?: Auth }).auth
  if (auth !== undefined) {
    return auth.user_data
  } else {
    return null
  }
}

// タブに出すタイトル（各ページの <Head title> の後ろにアプリ名を付ける）
function getDocumentTitle(title: string): string {
  const appName = 'コブチョウ'
  if (title !== '') {
    return `${title} | ${appName}`
  } else {
    return appName
  }
}

// 画面の分類（画面名の先頭のフォルダ。'auth/login' → 'auth'、'ledger' → 'ledger'）
function getPageGroup(name: string): string {
  const slashIndex = name.indexOf('/')
  if (slashIndex !== -1) {
    return name.slice(0, slashIndex)
  } else {
    return name
  }
}
