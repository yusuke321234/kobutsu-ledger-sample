import type { ReactNode } from 'react'
import { useFlashToast } from '@/hooks/use-flash-toast'

// 認証系画面（ログイン・登録・パスワード再設定）用レイアウト。中央寄せの1カラム（モバイル基準）。
export default function GuestLayout({ children }: { children: ReactNode }) {
  useFlashToast()

  return (
    <div className="flex min-h-screen items-center justify-center bg-[#FDFDFC] px-6 py-10">
      <main className="w-full max-w-sm">{children}</main>
    </div>
  )
}
