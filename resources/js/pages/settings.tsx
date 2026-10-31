import { Head, usePage } from '@inertiajs/react'
import AccountSection from '@/components/settings/account-section'
import LogoutSection from '@/components/settings/logout-section'
import OfficeSection from '@/components/settings/office-section'

// 設定。各まとまりは互いに状態を共有しないので、ボードを挟まずページに並べる。
// 表示するアカウント情報は全画面共有の auth から受け取り、必要なまとまりに渡す。
export default function Settings() {
  // ログイン必須の画面なので user は null にならない（型のための確認）
  const { auth } = usePage().props
  if (auth.user_data !== null) {
    // ok.
  } else {
    return null
  }
  const userData = auth.user_data

  //
  return (
    <div>
      <Head title="設定">
        <meta
          head-key="description"
          name="description"
          content="氏名・営業所名・古物商許可番号など、アカウントの設定を変更します。"
        />
      </Head>
      <h1 className="px-4 pt-6 text-xl font-semibold text-[#1b1b18]">設定</h1>
      <div className="px-4 pb-10">
        <AccountSection userData={userData} />
        <OfficeSection userData={userData} />
        <LogoutSection />
      </div>
    </div>
  )
}
